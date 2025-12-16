<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClientPriceCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientPriceCodeRequest extends FormRequest
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
        $clientId = $this->route('client')?->id;

        return [
            'service_type_id' => [
                'required',
                'integer',
                Rule::exists('service_types', 'id')->whereNull('deleted_at'),
                // Ensure no duplicate active price code for same client+service_type
                Rule::unique('client_price_codes')->where(function ($query) use ($clientId) {
                    return $query->where('client_id', $clientId)
                        ->where('is_active', true);
                }),
            ],
            'price_code' => 'nullable|string|max:64',
            'entry_fee_brutto' => 'required|integer|min:0',
            'trainer_fee_brutto' => 'required|integer|min:0',
            'currency' => 'sometimes|string|size:3',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'service_type_id.required' => 'A service type is required.',
            'service_type_id.exists' => 'The selected service type does not exist.',
            'service_type_id.unique' => 'An active price code already exists for this service type.',
            'entry_fee_brutto.required' => 'Entry fee is required.',
            'entry_fee_brutto.min' => 'Entry fee must be at least 0.',
            'trainer_fee_brutto.required' => 'Trainer fee is required.',
            'trainer_fee_brutto.min' => 'Trainer fee must be at least 0.',
            'valid_from.required' => 'Valid from date is required.',
            'valid_until.after' => 'Valid until must be after valid from date.',
        ];
    }
}
