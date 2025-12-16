<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('service_types', 'code'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'default_entry_fee_brutto' => 'required|integer|min:0',
            'default_trainer_fee_brutto' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'A service type code is required.',
            'code.unique' => 'This service type code already exists.',
            'code.alpha_dash' => 'The code may only contain letters, numbers, dashes, and underscores.',
            'name.required' => 'A service type name is required.',
            'default_entry_fee_brutto.required' => 'Default entry fee is required.',
            'default_entry_fee_brutto.min' => 'Default entry fee must be at least 0.',
            'default_trainer_fee_brutto.required' => 'Default trainer fee is required.',
            'default_trainer_fee_brutto.min' => 'Default trainer fee must be at least 0.',
        ];
    }
}
