<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;

class BookClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            // client_id is optional - defaults to authenticated user's client profile
            'client_id' => ['nullable', 'exists:clients,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $occurrenceId = $this->route('occurrenceId');
            $occurrence = \App\Models\ClassOccurrence::find($occurrenceId);

            if (!$occurrence) {
                $validator->errors()->add('occurrence_id', 'Class not found');
                return;
            }

            // Use provided client_id or authenticated user's client
            $clientId = $this->input('client_id') ?? $this->user()->client?->id;

            if (!$clientId) {
                $validator->errors()->add('client_id', 'No client profile found');
                return;
            }

            // Check if client already has a registration for this occurrence
            $existingRegistration = $occurrence->registrations()
                ->where('client_id', $clientId)
                ->whereIn('status', ['booked', 'waitlist'])
                ->exists();

            if ($existingRegistration) {
                $validator->errors()->add('client_id', 'Already registered for this class');
            }

            // Check if occurrence is in the past
            if ($occurrence->starts_at < now()) {
                $validator->errors()->add('occurrence_id', 'Cannot book classes in the past');
            }

            // Check if occurrence is cancelled
            if ($occurrence->status === 'cancelled') {
                $validator->errors()->add('occurrence_id', 'This class has been cancelled');
            }
        });
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Client ID is required',
            'client_id.exists' => 'Client not found',
        ];
    }
}
