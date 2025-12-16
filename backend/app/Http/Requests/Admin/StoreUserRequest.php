<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['client', 'staff', 'admin'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults()],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
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
