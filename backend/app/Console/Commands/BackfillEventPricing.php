<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\ClientPriceCode;
use App\Models\ServiceType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEventPricing extends Command
{
    protected $signature = 'events:backfill-pricing {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill pricing data for existing events based on service type and client price codes';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        $events = Event::where('type', 'INDIVIDUAL')
            ->whereNotNull('client_id')
            ->with(['client', 'additionalClients', 'serviceType'])
            ->get();

        $this->info("Found {$events->count()} individual events to process");

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($events as $event) {
            $this->line('');
            $this->info("Processing Event #{$event->id}: {$event->client->name} on {$event->start_time}");

            try {
                if (!$event->service_type_id) {
                    $this->warn("  - No service type assigned, skipping");
                    $skipped++;
                    continue;
                }

                $serviceType = $event->serviceType;
                if (!$serviceType) {
                    $this->warn("  - Service type not found, skipping");
                    $skipped++;
                    continue;
                }

                $this->line("  - Service type: {$serviceType->name}");
                $this->line("  - Default entry fee: {$serviceType->default_entry_fee_brutto}");
                $this->line("  - Default trainer fee: {$serviceType->default_trainer_fee_brutto}");

                if (!$dryRun) {
                    DB::beginTransaction();
                }

                // Update main client pricing
                $mainClientPricing = $this->resolvePricingForClient(
                    $event->client_id,
                    $event->service_type_id,
                    $serviceType
                );

                $this->line("  - Main client ({$event->client->name}):");
                $this->line("    Entry: {$mainClientPricing['entry_fee_brutto']}, Trainer: {$mainClientPricing['trainer_fee_brutto']}");
                $this->line("    Source: {$mainClientPricing['source']}");

                if (!$dryRun) {
                    $event->update([
                        'entry_fee_brutto' => $mainClientPricing['entry_fee_brutto'],
                        'trainer_fee_brutto' => $mainClientPricing['trainer_fee_brutto'],
                        'price_source' => $mainClientPricing['source'],
                    ]);
                }

                // Update additional clients pricing
                $additionalClients = $event->additionalClients;
                if ($additionalClients->count() > 0) {
                    $this->line("  - Additional clients ({$additionalClients->count()}):");

                    foreach ($additionalClients as $additionalClient) {
                        $additionalPricing = $this->resolvePricingForClient(
                            $additionalClient->id,
                            $event->service_type_id,
                            $serviceType
                        );

                        $this->line("    {$additionalClient->name}:");
                        $this->line("      Entry: {$additionalPricing['entry_fee_brutto']}, Trainer: {$additionalPricing['trainer_fee_brutto']}");
                        $this->line("      Source: {$additionalPricing['source']}");

                        if (!$dryRun) {
                            $event->additionalClients()->updateExistingPivot($additionalClient->id, [
                                'entry_fee_brutto' => $additionalPricing['entry_fee_brutto'],
                                'trainer_fee_brutto' => $additionalPricing['trainer_fee_brutto'],
                                'price_source' => $additionalPricing['source'],
                            ]);
                        }
                    }
                }

                if (!$dryRun) {
                    DB::commit();
                }

                $updated++;
                $this->info("  ✓ Event #{$event->id} " . ($dryRun ? 'would be updated' : 'updated'));

            } catch (\Exception $e) {
                if (!$dryRun) {
                    DB::rollBack();
                }
                $this->error("  ✗ Error processing event #{$event->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->line('');
        $this->info('=== Summary ===');
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        if ($dryRun) {
            $this->warn('This was a dry run - no changes were made. Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }

    private function resolvePricingForClient(int $clientId, int $serviceTypeId, ServiceType $serviceType): array
    {
        // First try exact service_type_id match
        $clientPriceCode = ClientPriceCode::where('client_id', $clientId)
            ->where('service_type_id', $serviceTypeId)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->first();

        if ($clientPriceCode) {
            return [
                'entry_fee_brutto' => $clientPriceCode->entry_fee_brutto,
                'trainer_fee_brutto' => $clientPriceCode->trainer_fee_brutto,
                'source' => 'client_price_code',
            ];
        }

        // Try to find by service type NAME (handles duplicate service types with different IDs)
        $serviceTypesWithSameName = ServiceType::where('name', $serviceType->name)->pluck('id');

        $clientPriceCode = ClientPriceCode::where('client_id', $clientId)
            ->whereIn('service_type_id', $serviceTypesWithSameName)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->first();

        if ($clientPriceCode) {
            return [
                'entry_fee_brutto' => $clientPriceCode->entry_fee_brutto,
                'trainer_fee_brutto' => $clientPriceCode->trainer_fee_brutto,
                'source' => 'client_price_code',
            ];
        }

        // Fall back to service type defaults
        return [
            'entry_fee_brutto' => $serviceType->default_entry_fee_brutto ?? 0,
            'trainer_fee_brutto' => $serviceType->default_trainer_fee_brutto ?? 0,
            'source' => 'service_type_default',
        ];
    }
}
