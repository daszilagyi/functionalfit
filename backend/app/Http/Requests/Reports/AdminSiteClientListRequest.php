<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class AdminSiteClientListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (admin only)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:' . now()->addYear()->toDateString()],
            'site' => ['required', 'integer', 'exists:sites,id'],
            'roomId' => ['nullable', 'integer', 'exists:rooms,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Start date is required',
            'to.required' => 'End date is required',
            'to.after_or_equal' => 'End date must be equal to or after start date',
            'to.before_or_equal' => 'Date range cannot exceed 1 year',
            'site.required' => 'Site is required',
            'site.exists' => 'The selected site does not exist',
            'roomId.exists' => 'The selected room does not exist',
        ];
    }
}
