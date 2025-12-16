<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $siteId = $this->route('site'); // For update requests

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'name')->ignore($siteId)->whereNull('deleted_at'),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('sites', 'slug')->ignore($siteId)->whereNull('deleted_at'),
            ],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string|max:1000',
            'opening_hours' => 'nullable|array',
            'opening_hours.monday' => 'nullable|array',
            'opening_hours.monday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.monday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.tuesday' => 'nullable|array',
            'opening_hours.tuesday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.tuesday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.wednesday' => 'nullable|array',
            'opening_hours.wednesday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.wednesday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.thursday' => 'nullable|array',
            'opening_hours.thursday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.thursday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.friday' => 'nullable|array',
            'opening_hours.friday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.friday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.saturday' => 'nullable|array',
            'opening_hours.saturday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.saturday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.sunday' => 'nullable|array',
            'opening_hours.sunday.open' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'opening_hours.sunday.close' => 'nullable|string|regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Site name is required',
            'name.unique' => 'A site with this name already exists',
            'slug.unique' => 'A site with this slug already exists',
            'slug.regex' => 'Slug must contain only lowercase letters, numbers, and hyphens',
            'email.email' => 'Invalid email format',
            'opening_hours.*.open.regex' => 'Opening time must be in HH:MM format (e.g., 08:00)',
            'opening_hours.*.close.regex' => 'Closing time must be in HH:MM format (e.g., 22:00)',
        ];
    }
}
