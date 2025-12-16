<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingPricingException;
use App\Models\Client;
use App\Models\ClassOccurrence;
use App\Models\ClientClassPricing;
use App\Models\ClassPricingDefault;
use Carbon\Carbon;

class PricingService
{
    /**
     * Resolve the applicable price for a client and class occurrence.
     *
     * Priority logic (from spec section 4.1):
     * 1. Client + occurrence specific (client_class_pricing with class_occurrence_id)
     * 2. Client + template general (client_class_pricing with class_template_id)
     * 3. Template default (class_pricing_defaults)
     * 4. Throw MissingPricingException if no price found
     *
     * @param Client $client
     * @param ClassOccurrence $occurrence
     * @param Carbon $atTime The time to check validity (defaults to now)
     * @return array{entry_fee_brutto: int, trainer_fee_brutto: int, currency: string, source: string}
     * @throws MissingPricingException
     */
    public function resolvePrice(Client $client, ClassOccurrence $occurrence, ?Carbon $atTime = null): array
    {
        $atTime = $atTime ?? now();

        // Priority 1: Client + occurrence specific
        $occurrenceSpecific = ClientClassPricing::forClientAndOccurrence($client->id, $occurrence->id)
            ->validAt($atTime)
            ->first();

        if ($occurrenceSpecific) {
            return [
                'entry_fee_brutto' => $occurrenceSpecific->entry_fee_brutto,
                'trainer_fee_brutto' => $occurrenceSpecific->trainer_fee_brutto,
                'currency' => $occurrenceSpecific->currency,
                'source' => 'client_occurrence_specific',
                'pricing_id' => $occurrenceSpecific->id,
            ];
        }

        // Priority 2: Client + template general
        $templateSpecific = ClientClassPricing::forClientAndTemplate($client->id, $occurrence->template_id)
            ->validAt($atTime)
            ->first();

        if ($templateSpecific) {
            return [
                'entry_fee_brutto' => $templateSpecific->entry_fee_brutto,
                'trainer_fee_brutto' => $templateSpecific->trainer_fee_brutto,
                'currency' => $templateSpecific->currency,
                'source' => 'client_template_specific',
                'pricing_id' => $templateSpecific->id,
            ];
        }

        // Priority 3: Template default
        $templateDefault = ClassPricingDefault::where('class_template_id', $occurrence->template_id)
            ->active()
            ->validAt($atTime)
            ->first();

        if ($templateDefault) {
            return [
                'entry_fee_brutto' => $templateDefault->entry_fee_brutto,
                'trainer_fee_brutto' => $templateDefault->trainer_fee_brutto,
                'currency' => $templateDefault->currency,
                'source' => 'template_default',
                'pricing_id' => $templateDefault->id,
            ];
        }

        // Priority 4: No pricing found - throw exception
        throw new MissingPricingException(
            'No pricing configuration found for this class and client combination',
            [
                'client_id' => $client->id,
                'class_occurrence_id' => $occurrence->id,
                'class_template_id' => $occurrence->template_id,
                'checked_at' => $atTime->toIso8601String(),
            ]
        );
    }

    /**
     * Calculate settlement totals for a trainer in a given period.
     *
     * Returns registrations that should be included in settlement based on:
     * - Status: attended, no_show, cancelled (with late cancellation rules)
     * - System settings for no_show and cancellation fee handling
     *
     * @param int $trainerId
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @return array{total_trainer_fee: int, total_entry_fee: int, items: array}
     */
    public function calculateSettlementForTrainer(int $trainerId, Carbon $periodStart, Carbon $periodEnd): array
    {
        // Get all class occurrences in the period for this trainer
        $occurrences = ClassOccurrence::where('trainer_id', $trainerId)
            ->whereBetween('starts_at', [$periodStart, $periodEnd])
            ->with(['registrations.client', 'template'])
            ->get();

        $totalTrainerFee = 0;
        $totalEntryFee = 0;
        $items = [];

        // Get system settings for no_show and cancellation handling
        // For now, we'll use default behavior: attended only
        // TODO: Implement system settings retrieval when settings table is configured

        foreach ($occurrences as $occurrence) {
            foreach ($occurrence->registrations as $registration) {
                // Determine if this registration should be included in settlement
                if (!$this->shouldIncludeInSettlement($registration->status)) {
                    continue;
                }

                try {
                    // Resolve pricing for this client-occurrence combination
                    $pricing = $this->resolvePrice(
                        $registration->client,
                        $occurrence,
                        $occurrence->starts_at
                    );

                    // Determine actual fees based on status and business rules
                    $fees = $this->calculateFeesForStatus($registration->status, $pricing);

                    $totalTrainerFee += $fees['trainer_fee'];
                    $totalEntryFee += $fees['entry_fee'];

                    $items[] = [
                        'class_occurrence_id' => $occurrence->id,
                        'client_id' => $registration->client_id,
                        'registration_id' => $registration->id,
                        'entry_fee_brutto' => $fees['entry_fee'],
                        'trainer_fee_brutto' => $fees['trainer_fee'],
                        'currency' => $pricing['currency'],
                        'status' => $registration->status,
                        // Additional info for preview
                        'class_name' => $occurrence->template->title ?? 'Unknown',
                        'client_name' => $registration->client->full_name ?? 'Unknown',
                        'class_date' => $occurrence->starts_at->toIso8601String(),
                    ];
                } catch (MissingPricingException $e) {
                    // Log this but continue processing other registrations
                    // In production, you might want to track these for admin review
                    \Log::warning('Missing pricing for settlement calculation', [
                        'trainer_id' => $trainerId,
                        'occurrence_id' => $occurrence->id,
                        'client_id' => $registration->client_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'total_trainer_fee' => $totalTrainerFee,
            'total_entry_fee' => $totalEntryFee,
            'items' => $items,
        ];
    }

    /**
     * Determine if a registration should be included in settlement.
     */
    private function shouldIncludeInSettlement(string $status): bool
    {
        // attended always counts
        if ($status === 'attended') {
            return true;
        }

        // For no_show and cancelled, this would check system settings
        // For now, only include attended
        // TODO: Implement settings-based logic when settings system is ready

        return false;
    }

    /**
     * Calculate actual fees based on registration status and business rules.
     */
    private function calculateFeesForStatus(string $status, array $pricing): array
    {
        // Default: full fees for attended
        if ($status === 'attended') {
            return [
                'entry_fee' => $pricing['entry_fee_brutto'],
                'trainer_fee' => $pricing['trainer_fee_brutto'],
            ];
        }

        // For no_show: business rule decision
        // Option A: entry_fee counts, trainer_fee does not
        // Option B: neither counts
        // Currently implementing Option B (lenient)
        if ($status === 'no_show') {
            return [
                'entry_fee' => 0,
                'trainer_fee' => 0,
            ];
        }

        // For cancelled: depends on timing and rules
        // Currently: neither counts
        if ($status === 'cancelled') {
            return [
                'entry_fee' => 0,
                'trainer_fee' => 0,
            ];
        }

        // Default fallback
        return [
            'entry_fee' => 0,
            'trainer_fee' => 0,
        ];
    }
}
