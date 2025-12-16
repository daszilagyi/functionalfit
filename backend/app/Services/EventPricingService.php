<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceType;

class EventPricingService
{
    public function __construct(
        private readonly PriceCodeService $priceCodeService
    ) {}

    /**
     * Resolve pricing for a client given a service type.
     * Returns pricing array suitable for storing in events or pivot table.
     *
     * @param int $clientId The client ID
     * @param int $serviceTypeId The service type ID
     * @return array{entry_fee_brutto: int, trainer_fee_brutto: int, currency: string, price_source: string}
     */
    public function resolvePricingForClient(int $clientId, int $serviceTypeId): array
    {
        try {
            $pricing = $this->priceCodeService->resolveByClientAndServiceType($clientId, $serviceTypeId);

            return [
                'entry_fee_brutto' => $pricing['entry_fee_brutto'],
                'trainer_fee_brutto' => $pricing['trainer_fee_brutto'],
                'currency' => $pricing['currency'] ?? 'HUF',
                'price_source' => $pricing['source'],
            ];
        } catch (\Exception $e) {
            // Fallback to service type defaults
            return $this->resolvePricingForTechnicalGuest($serviceTypeId);
        }
    }

    /**
     * Resolve pricing for a technical guest (uses service type defaults).
     *
     * @param int $serviceTypeId The service type ID
     * @return array{entry_fee_brutto: int, trainer_fee_brutto: int, currency: string, price_source: string}
     */
    public function resolvePricingForTechnicalGuest(int $serviceTypeId): array
    {
        $serviceType = ServiceType::find($serviceTypeId);

        return [
            'entry_fee_brutto' => $serviceType?->default_entry_fee_brutto ?? 0,
            'trainer_fee_brutto' => $serviceType?->default_trainer_fee_brutto ?? 0,
            'currency' => 'HUF',
            'price_source' => 'service_type_default',
        ];
    }
}
