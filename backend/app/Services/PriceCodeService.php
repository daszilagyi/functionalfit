<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingPricingException;
use App\Models\Client;
use App\Models\ClientPriceCode;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\Carbon;

class PriceCodeService
{
    /**
     * Resolve pricing by client email and service type code.
     * Used by staff UI when creating events.
     *
     * @param string $clientEmail
     * @param string $serviceTypeCode
     * @return array{entry_fee_brutto: int, trainer_fee_brutto: int, currency: string, source: string, price_code?: string}
     * @throws MissingPricingException
     */
    public function resolveByEmailAndServiceType(
        string $clientEmail,
        string $serviceTypeCode
    ): array {
        // 1. Find active service type by code
        $serviceType = ServiceType::byCode($serviceTypeCode)
            ->active()
            ->first();

        if (!$serviceType) {
            throw new MissingPricingException("Service type not found: {$serviceTypeCode}");
        }

        // 2. Find client by email (via users table)
        $user = User::where('email', $clientEmail)->first();
        if (!$user) {
            // Return service type defaults if no user found
            return $this->formatServiceTypeDefault($serviceType);
        }

        $client = Client::where('user_id', $user->id)->first();
        if (!$client) {
            return $this->formatServiceTypeDefault($serviceType);
        }

        // 3. Query client_price_codes for active, valid price
        $priceCode = ClientPriceCode::forClientAndServiceType($client->id, $serviceType->id)
            ->active()
            ->validAt(Carbon::now())
            ->orderBy('valid_from', 'desc')
            ->first();

        if ($priceCode) {
            return [
                'entry_fee_brutto' => $priceCode->entry_fee_brutto,
                'trainer_fee_brutto' => $priceCode->trainer_fee_brutto,
                'currency' => $priceCode->currency,
                'source' => 'client_price_code',
                'price_code' => $priceCode->price_code,
            ];
        }

        // 4. Fallback to service type defaults
        return $this->formatServiceTypeDefault($serviceType);
    }

    /**
     * Resolve pricing by client ID and service type ID.
     * Used when we already know the client and service type.
     *
     * @param int $clientId
     * @param int $serviceTypeId
     * @return array{entry_fee_brutto: int, trainer_fee_brutto: int, currency: string, source: string, price_code?: string}
     * @throws MissingPricingException
     */
    public function resolveByClientAndServiceType(
        int $clientId,
        int $serviceTypeId
    ): array {
        $serviceType = ServiceType::find($serviceTypeId);

        if (!$serviceType) {
            throw new MissingPricingException("Service type not found: ID {$serviceTypeId}");
        }

        // Query client_price_codes for active, valid price
        $priceCode = ClientPriceCode::forClientAndServiceType($clientId, $serviceTypeId)
            ->active()
            ->validAt(Carbon::now())
            ->orderBy('valid_from', 'desc')
            ->first();

        if ($priceCode) {
            return [
                'entry_fee_brutto' => $priceCode->entry_fee_brutto,
                'trainer_fee_brutto' => $priceCode->trainer_fee_brutto,
                'currency' => $priceCode->currency,
                'source' => 'client_price_code',
                'price_code' => $priceCode->price_code,
            ];
        }

        return $this->formatServiceTypeDefault($serviceType);
    }

    /**
     * Generate default price codes for a client on all active service types.
     * Called during client registration.
     *
     * @param Client $client
     * @param int|null $createdBy User ID who created the client
     * @return void
     */
    public function generateDefaultPriceCodes(Client $client, ?int $createdBy = null): void
    {
        // Get client email from user
        $email = $client->user?->email ?? '';

        if (empty($email)) {
            return; // Cannot create price codes without email
        }

        $activeServiceTypes = ServiceType::active()->get();

        foreach ($activeServiceTypes as $serviceType) {
            // Check if price code already exists for this client and service type
            $exists = ClientPriceCode::where('client_id', $client->id)
                ->where('service_type_id', $serviceType->id)
                ->exists();

            if (!$exists) {
                ClientPriceCode::create([
                    'client_id' => $client->id,
                    'client_email' => $email,
                    'service_type_id' => $serviceType->id,
                    'entry_fee_brutto' => $serviceType->default_entry_fee_brutto,
                    'trainer_fee_brutto' => $serviceType->default_trainer_fee_brutto,
                    'currency' => 'HUF',
                    'valid_from' => Carbon::now(),
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]);
            }
        }
    }

    /**
     * Generate default price codes for a specific service type for all existing clients.
     * Called when a new service type is created.
     *
     * @param ServiceType $serviceType
     * @param int|null $createdBy User ID who created the service type
     * @return int Number of price codes created
     */
    public function generatePriceCodesForNewServiceType(ServiceType $serviceType, ?int $createdBy = null): int
    {
        $count = 0;

        // Get all clients with user accounts (email is available via user)
        $clients = Client::whereHas('user')->with('user')->get();

        foreach ($clients as $client) {
            $email = $client->user?->email ?? '';

            if (empty($email)) {
                continue;
            }

            // Check if price code already exists
            $exists = ClientPriceCode::where('client_id', $client->id)
                ->where('service_type_id', $serviceType->id)
                ->exists();

            if (!$exists) {
                ClientPriceCode::create([
                    'client_id' => $client->id,
                    'client_email' => $email,
                    'service_type_id' => $serviceType->id,
                    'entry_fee_brutto' => $serviceType->default_entry_fee_brutto,
                    'trainer_fee_brutto' => $serviceType->default_trainer_fee_brutto,
                    'currency' => 'HUF',
                    'valid_from' => Carbon::now(),
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Update client email in all their price codes.
     * Called when a user's email changes.
     *
     * @param Client $client
     * @param string $newEmail
     * @return void
     */
    public function updateClientEmail(Client $client, string $newEmail): void
    {
        ClientPriceCode::where('client_id', $client->id)
            ->update(['client_email' => $newEmail]);
    }

    /**
     * Format service type defaults as response.
     *
     * @param ServiceType $serviceType
     * @return array{entry_fee_brutto: int, trainer_fee_brutto: int, currency: string, source: string}
     */
    private function formatServiceTypeDefault(ServiceType $serviceType): array
    {
        return [
            'entry_fee_brutto' => $serviceType->default_entry_fee_brutto,
            'trainer_fee_brutto' => $serviceType->default_trainer_fee_brutto,
            'currency' => 'HUF',
            'source' => 'service_type_default',
        ];
    }
}
