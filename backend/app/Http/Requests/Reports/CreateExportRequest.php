<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $allowedReportKeys = [
            'admin.trainer-summary',
            'admin.site-client-list',
            'admin.finance-overview',
            'staff.my-summary',
            'staff.my-clients',
            'staff.my-trends',
            'client.my-activity',
            'client.my-finance',
        ];

        return [
            'report_key' => ['required', 'string', Rule::in($allowedReportKeys)],
            'params' => ['required', 'array'],
            'params.from' => ['required', 'date'],
            'params.to' => ['required', 'date', 'after_or_equal:params.from'],
            'format' => ['sometimes', Rule::in(['xlsx', 'csv', 'json'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'report_key.required' => 'Report key is required',
            'report_key.in' => 'Invalid report key',
            'params.required' => 'Report parameters are required',
            'params.from.required' => 'Start date is required in parameters',
            'params.to.required' => 'End date is required in parameters',
            'params.to.after_or_equal' => 'End date must be equal to or after start date',
            'format.in' => 'Format must be xlsx, csv, or json',
        ];
    }
}
