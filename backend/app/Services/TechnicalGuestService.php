<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing the special "Technical Guest" client.
 *
 * The Technical Guest is a special client without a user account,
 * used for unknown/walk-in participants.
 */
class TechnicalGuestService
{
    /**
     * Get the Technical Guest client ID from settings.
     *
     * @return int|null The client ID or null if not configured
     */
    public static function getId(): ?int
    {
        $value = DB::table('settings')
            ->where('key', 'technical_guest_client_id')
            ->value('value');

        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value);

        return is_int($decoded) ? $decoded : null;
    }

    /**
     * Get the Technical Guest Client model instance.
     *
     * @return Client|null The Client model or null if not found
     */
    public static function getClient(): ?Client
    {
        $id = self::getId();

        if ($id === null) {
            return null;
        }

        return Client::find($id);
    }

    /**
     * Check if a given client ID is the Technical Guest.
     *
     * @param int $clientId The client ID to check
     * @return bool True if the client is the Technical Guest
     */
    public static function isTechnicalGuest(int $clientId): bool
    {
        $technicalGuestId = self::getId();

        return $technicalGuestId !== null && $technicalGuestId === $clientId;
    }
}
