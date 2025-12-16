<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PolicyViolationException;
use App\Models\Client;
use App\Models\Pass;
use Illuminate\Support\Facades\DB;

class PassCreditService
{
    /**
     * Check if client has available credits.
     */
    public function hasAvailableCredits(Client $client): bool
    {
        return Pass::where('client_id', $client->id)
            ->active()
            ->exists();
    }

    /**
     * Get the next available pass for the client.
     */
    public function getAvailablePass(Client $client): ?Pass
    {
        return Pass::where('client_id', $client->id)
            ->active()
            ->orderBy('valid_until', 'asc') // Use passes that expire soonest first
            ->orderBy('created_at', 'asc')
            ->first();
    }

    /**
     * Deduct one credit from client's pass.
     *
     * @throws PolicyViolationException
     */
    public function deductCredit(Client $client, string $reason): Pass
    {
        return DB::transaction(function () use ($client, $reason) {
            $pass = $this->getAvailablePass($client);

            if (!$pass) {
                throw new PolicyViolationException(
                    'No active pass with available credits found',
                    ['client_id' => $client->id, 'reason' => $reason]
                );
            }

            // Lock the pass for this transaction
            $pass = Pass::lockForUpdate()->find($pass->id);

            if ($pass->credits_left <= 0) {
                throw new PolicyViolationException(
                    'Pass has no remaining credits',
                    ['pass_id' => $pass->id, 'reason' => $reason]
                );
            }

            $pass->credits_left--;

            // Auto-mark as depleted if no credits left
            if ($pass->credits_left <= 0 && $pass->status === 'active') {
                $pass->status = 'depleted';
            }

            $pass->save();

            return $pass;
        });
    }

    /**
     * Refund credits to client's pass.
     */
    public function refundCredit(Client $client, int $credits = 1, string $reason = '', ?int $passId = null): Pass
    {
        return DB::transaction(function () use ($client, $credits, $reason, $passId) {
            // If pass ID is provided, refund to that specific pass
            if ($passId) {
                $pass = Pass::lockForUpdate()
                    ->where('id', $passId)
                    ->where('client_id', $client->id)
                    ->firstOrFail();
            } else {
                // Otherwise, find the most recently used pass
                $pass = Pass::lockForUpdate()
                    ->where('client_id', $client->id)
                    ->where('credits_left', '<', DB::raw('total_credits'))
                    ->orderBy('updated_at', 'desc')
                    ->firstOrFail();
            }

            $pass->credits_left += $credits;

            // Reactivate if it was marked as depleted
            if ($pass->status === 'depleted' && $pass->credits_left > 0) {
                $pass->status = 'active';
            }

            $pass->save();

            return $pass;
        });
    }

    /**
     * Get total available credits for a client across all active passes.
     */
    public function getTotalAvailableCredits(Client $client): int
    {
        return Pass::where('client_id', $client->id)
            ->active()
            ->sum('credits_left');
    }
}
