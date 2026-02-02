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
        $roomId = $this->route('room');

        $googleCalendarIdRule = ['nullable', 'string'];
        if ($isUpdate && $roomId) {
            $googleCalendarIdRule[] = Rule::unique('rooms', 'google_calendar_id')->ignore($roomId);
        } else {
            $googleCalendarIdRule[] = 'unique:rooms,google_calendar_id';
        }

        return [
            'site_id' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'exists:sites,id'],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'google_calendar_id' => $googleCalendarIdRule,
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
