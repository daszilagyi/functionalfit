<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (admin only)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'trainer_id.required' => 'Trainer is required',
            'trainer_id.exists' => 'The selected trainer does not exist',
            'period_start.required' => 'Period start date is required',
            'period_end.required' => 'Period end date is required',
            'period_end.after_or_equal' => 'Period end date must be equal to or after period start date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_by' => $this->user()?->id,
        ]);
    }
}
