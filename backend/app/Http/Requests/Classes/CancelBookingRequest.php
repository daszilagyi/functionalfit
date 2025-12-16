<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization logic handled in controller/policy
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

            // Check if registration exists
            $registration = $occurrence->registrations()
                ->where('client_id', $clientId)
                ->whereIn('status', ['booked', 'waitlist'])
                ->first();

            if (!$registration) {
                $validator->errors()->add('client_id', 'No active registration found for this class');
                return;
            }

            // Check cancellation window (24 hours for free cancellation)
            $hoursUntilClass = Carbon::parse($occurrence->starts_at)->diffInHours(now(), false);

            if ($hoursUntilClass < 24 && !$this->user()->isAdmin()) {
                // Less than 24 hours notice - may incur credit deduction
                // This will be handled in the service layer based on settings
                $this->merge(['late_cancellation' => true]);
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
