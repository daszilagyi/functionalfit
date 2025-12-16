<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CalendarChangeLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarChangeFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (admin or staff)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * Accepts both snake_case (from frontend) and camelCase parameters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Accept both snake_case and camelCase
            'actor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'site' => ['nullable', 'string', Rule::in(['SASAD', 'TB', 'ÚJBUDA'])],
            'action' => ['nullable', 'string', Rule::in(CalendarChangeLog::getActionTypes())],
            'changed_from' => ['nullable', 'date'],
            'changed_to' => ['nullable', 'date', 'after_or_equal:changed_from'],
            'sort' => ['nullable', 'string', Rule::in([
                'changed_at', 'action', 'actor_name', 'site', 'room_name', 'starts_at'
            ])],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'actor_user_id.exists' => 'The selected actor user does not exist',
            'room_id.exists' => 'The selected room does not exist',
            'site.in' => 'The site must be one of: SASAD, TB, ÚJBUDA',
            'action.in' => 'The action must be one of: EVENT_CREATED, EVENT_UPDATED, EVENT_DELETED',
            'changed_from.date' => 'The changed_from must be a valid date',
            'changed_to.date' => 'The changed_to must be a valid date',
            'changed_to.after_or_equal' => 'The changed_to date must be equal to or after changed_from date',
            'sort.in' => 'Invalid sort field',
            'order.in' => 'The order must be either asc or desc',
            'per_page.max' => 'Maximum 100 items per page allowed',
        ];
    }

    /**
     * Get the validated data with defaults applied.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        // Apply defaults
        $data['sort'] = $data['sort'] ?? 'changed_at';
        $data['order'] = $data['order'] ?? 'desc';
        $data['page'] = $data['page'] ?? 1;
        $data['per_page'] = $data['per_page'] ?? 50;

        return $data;
    }
}
