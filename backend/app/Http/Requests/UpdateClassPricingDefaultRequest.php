<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassPricingDefaultRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'class_template_id' => ['sometimes', 'integer', 'exists:class_templates,id'],
            'entry_fee_brutto' => ['sometimes', 'integer', 'min:0'],
            'trainer_fee_brutto' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:3', 'in:HUF,EUR,USD'],
            'valid_from' => ['sometimes', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'class_template_id.exists' => 'The selected class template does not exist',
            'entry_fee_brutto.min' => 'Entry fee must be at least 0',
            'trainer_fee_brutto.min' => 'Trainer fee must be at least 0',
            'valid_until.after_or_equal' => 'Valid until date must be after or equal to valid from date',
        ];
    }
}
