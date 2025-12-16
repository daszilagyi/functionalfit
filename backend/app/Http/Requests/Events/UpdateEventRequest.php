<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UpdateEventRequest extends FormRequest
{
    protected $event = null;

    public function authorize(): bool
    {
        // Authorization is handled in the controller via policy
        return true;
    }

    public function getEvent(): ?Event
    {
        if ($this->event === null) {
            $eventId = $this->route('id');
            if ($eventId) {
                $this->event = Event::with('staff')->findOrFail($eventId);
            }
        }
        return $this->event;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['INDIVIDUAL', 'BLOCK'])],
            'client_id' => ['sometimes', 'nullable', 'exists:clients,id'],
            'additional_client_ids' => ['sometimes', 'nullable', 'array'],
            'additional_client_ids.*' => ['integer'], // Allow negative IDs for technical guests
            'service_type_id' => ['sometimes', 'nullable', 'integer', 'exists:service_types,id'],
            'room_id' => ['sometimes', 'exists:rooms,id'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
            'status' => ['sometimes', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Prepare data for validation
     * Calculate ends_at from starts_at + duration_minutes if duration_minutes is provided
     */
    protected function prepareForValidation(): void
    {
        // If duration_minutes is provided with starts_at, calculate ends_at
        if ($this->has('duration_minutes') && $this->has('starts_at')) {
            $startsAt = Carbon::parse($this->input('starts_at'));
            $durationMinutes = (int) $this->input('duration_minutes');
            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $this->merge([
                'ends_at' => $endsAt->toIso8601String(),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $eventId = $this->route('id');

            if (!$eventId) {
                return;
            }

            $event = Event::find($eventId);

            // If starts_at is being changed, validate same-day-only rule for staff
            if ($event && $this->has('starts_at') && !$user->isAdmin()) {
                $newStartsAt = Carbon::parse($this->input('starts_at'));
                $originalStartsAt = Carbon::parse($event->starts_at);

                if (!$newStartsAt->isSameDay($originalStartsAt)) {
                    $validator->errors()->add(
                        'starts_at',
                        'Staff can only move events within the same day. Contact admin for cross-day moves.'
                    );
                }
            }

            // Validate that client_id is not in additional_client_ids
            if ($this->filled('client_id') && $this->filled('additional_client_ids')) {
                $clientId = $this->input('client_id');
                $additionalIds = $this->input('additional_client_ids', []);

                if ($clientId && in_array($clientId, $additionalIds)) {
                    $validator->errors()->add(
                        'additional_client_ids',
                        'The main client cannot also be listed as an additional guest.'
                    );
                }
            }

            // Validate no duplicate regular clients (but allow Technical Guest duplicates)
            if ($this->has('additional_client_ids')) {
                $additionalIds = $this->input('additional_client_ids', []);

                // Filter out all technical guest IDs (any negative ID) and check for duplicates in remaining IDs
                $regularClientIds = array_filter($additionalIds, fn($id) => $id >= 0);

                // Check for duplicate regular clients
                if (count($regularClientIds) !== count(array_unique($regularClientIds))) {
                    $validator->errors()->add(
                        'additional_client_ids',
                        'Regular clients cannot be added multiple times. Only technical guests (negative IDs) can appear multiple times.'
                    );
                }

                // Validate that positive IDs exist in the database
                if (!empty($regularClientIds)) {
                    $existingClientIds = \App\Models\Client::whereIn('id', $regularClientIds)->pluck('id')->toArray();
                    $missingIds = array_diff($regularClientIds, $existingClientIds);

                    if (!empty($missingIds)) {
                        $validator->errors()->add(
                            'additional_client_ids',
                            'The following client IDs do not exist: ' . implode(', ', $missingIds)
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'End time must be after start time',
        ];
    }
}
