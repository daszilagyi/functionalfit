<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('email_templates', 'slug'),
            ],
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'html_body' => [
                'required',
                'string',
            ],
            'fallback_body' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'A template slug is required',
            'slug.unique' => 'This template slug is already in use',
            'slug.regex' => 'Slug must contain only lowercase letters, numbers, hyphens, and underscores',
            'subject.required' => 'Email subject is required',
            'html_body.required' => 'HTML body content is required',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'template slug',
            'html_body' => 'HTML body',
            'fallback_body' => 'fallback body',
        ];
    }
}
