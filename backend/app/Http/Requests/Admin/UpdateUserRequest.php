<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        // Route parameter is the user ID directly (integer), not User model
        $userId = $this->route('user');

        return [
            // User fields
            'role' => ['sometimes', Rule::in(['client', 'staff', 'admin'])],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['sometimes', 'string', Password::defaults()],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            // Client-specific fields
            'date_of_birth' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Staff-specific fields
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'default_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'is_available_for_booking' => ['nullable', 'boolean'],
            'daily_schedule_notification' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use',
            'role.in' => 'Invalid role specified',
        ];
    }
}
