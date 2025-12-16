<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffMySummaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (staff/admin)
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
            'groupBy' => ['required', Rule::in(['site', 'room', 'service_type'])],
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
            'groupBy.required' => 'Group by parameter is required',
            'groupBy.in' => 'Group by must be "site", "room", or "service_type"',
        ];
    }
}
