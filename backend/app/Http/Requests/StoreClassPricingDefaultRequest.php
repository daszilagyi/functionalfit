<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassPricingDefaultRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:100'],
            'class_template_id' => ['required', 'integer', 'exists:class_templates,id'],
            'entry_fee_brutto' => ['required', 'integer', 'min:0'],
            'trainer_fee_brutto' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3', 'in:HUF,EUR,USD'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'class_template_id.required' => 'Class template is required',
            'class_template_id.exists' => 'The selected class template does not exist',
            'entry_fee_brutto.required' => 'Entry fee is required',
            'entry_fee_brutto.min' => 'Entry fee must be at least 0',
            'trainer_fee_brutto.required' => 'Trainer fee is required',
            'trainer_fee_brutto.min' => 'Trainer fee must be at least 0',
            'valid_from.required' => 'Valid from date is required',
            'valid_until.after' => 'Valid until date must be after valid from date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'created_by' => $this->user()?->id,
            'currency' => $this->currency ?? 'HUF',
            'is_active' => $this->is_active ?? true,
        ]);
    }
}
