<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffPriceCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isAdmin() || $user->isStaff());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price_code' => 'nullable|string|max:64',
            'entry_fee_brutto' => 'sometimes|integer|min:0',
            'trainer_fee_brutto' => 'sometimes|integer|min:0',
            'currency' => 'sometimes|string|size:3',
            'valid_from' => 'sometimes|date',
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
            'entry_fee_brutto.min' => 'Entry fee must be at least 0.',
            'trainer_fee_brutto.min' => 'Trainer fee must be at least 0.',
            'valid_until.after' => 'Valid until must be after valid from date.',
        ];
    }
}
