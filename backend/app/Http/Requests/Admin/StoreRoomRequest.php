<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PATCH') || $this->isMethod('PUT');

        return [
            'site_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'google_calendar_id' => ['nullable', 'string', 'unique:rooms,google_calendar_id'],
            'color' => ['nullable', 'string', 'max:7'], // Hex color code
            'capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.exists' => 'Invalid site specified',
            'google_calendar_id.unique' => 'This Google Calendar ID is already assigned to another room',
        ];
    }
}
