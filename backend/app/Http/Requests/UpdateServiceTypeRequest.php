<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceTypeRequest extends FormRequest
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
        $serviceTypeId = $this->route('service_type')?->id ?? $this->route('serviceType')?->id;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('service_types', 'code')->ignore($serviceTypeId),
            ],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'default_entry_fee_brutto' => 'sometimes|integer|min:0',
            'default_trainer_fee_brutto' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This service type code already exists.',
            'code.alpha_dash' => 'The code may only contain letters, numbers, dashes, and underscores.',
            'default_entry_fee_brutto.min' => 'Default entry fee must be at least 0.',
            'default_trainer_fee_brutto.min' => 'Default trainer fee must be at least 0.',
        ];
    }
}
