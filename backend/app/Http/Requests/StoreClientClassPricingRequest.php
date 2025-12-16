<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientClassPricingRequest extends FormRequest
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
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'class_template_id' => [
                'required_without:class_occurrence_id',
                'nullable',
                'integer',
                'exists:class_templates,id',
            ],
            'class_occurrence_id' => [
                'required_without:class_template_id',
                'nullable',
                'integer',
                'exists:class_occurrences,id',
            ],
            'entry_fee_brutto' => ['required', 'integer', 'min:0'],
            'trainer_fee_brutto' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3', 'in:HUF,EUR,USD'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'source' => ['nullable', 'string', 'in:manual,import,promotion'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Client is required',
            'client_id.exists' => 'The selected client does not exist',
            'class_template_id.required_without' => 'Either class template or class occurrence is required',
            'class_occurrence_id.required_without' => 'Either class template or class occurrence is required',
            'class_template_id.exists' => 'The selected class template does not exist',
            'class_occurrence_id.exists' => 'The selected class occurrence does not exist',
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
            'source' => $this->source ?? 'manual',
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one of template_id or occurrence_id is provided
            if (!$this->class_template_id && !$this->class_occurrence_id) {
                $validator->errors()->add(
                    'class_template_id',
                    'Either class template or class occurrence must be specified'
                );
            }
        });
    }
}
