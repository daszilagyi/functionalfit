<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Event::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['INDIVIDUAL', 'BLOCK'])],
            'client_id' => ['nullable', 'integer'],
            'additional_client_ids' => ['nullable', 'array'],
            'additional_client_ids.*' => ['integer'], // Allow negative IDs for technical guests
            'service_type_id' => ['nullable', 'integer', 'exists:service_types,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that INDIVIDUAL events have at least one guest and a service type
            if ($this->input('type') === 'INDIVIDUAL') {
                $clientId = $this->input('client_id');
                $additionalIds = $this->input('additional_client_ids', []);

                if (!$clientId && empty($additionalIds)) {
                    $validator->errors()->add(
                        'client_id',
                        'Individual events must have at least one guest (either client_id or additional_client_ids).'
                    );
                }

                // Service type is required for INDIVIDUAL events
                if (!$this->filled('service_type_id')) {
                    $validator->errors()->add(
                        'service_type_id',
                        'Service type is required for individual events.'
                    );
                }
            }

            // Validate that positive client_id exists in database
            if ($this->filled('client_id') && $this->input('client_id') > 0) {
                $clientId = $this->input('client_id');
                if (!\App\Models\Client::where('id', $clientId)->exists()) {
                    $validator->errors()->add(
                        'client_id',
                        'The selected client id is invalid.'
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
            'client_id.required_if' => 'Client is required for individual events',
            'service_type_id.required' => 'Service type is required for individual events',
            'service_type_id.exists' => 'The selected service type does not exist',
            'starts_at.after' => 'Event must be scheduled in the future',
            'ends_at.after' => 'End time must be after start time',
        ];
    }
}
