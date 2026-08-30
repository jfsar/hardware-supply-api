<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Filter params for a synchronous report query (FR-RPT-001…003). The
 * report type comes from the route segment; only date-window and
 * pagination fields are accepted, and the window is capped so no single
 * query can walk more than the configured maximum (NFR-PERF-005).
 */
class AdminReportRequest extends FormRequest
{
    /**
     * Route middleware enforces reports.view.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to', 'after_or_equal:2000-01-01'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('reports.per_page', 100)],
        ];
    }

    /**
     * Reject windows wider than the configured maximum (default 366 days).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $from = $this->filled('date_from') ? $this->date('date_from') : now()->subDays(30);
            $to = $this->filled('date_to') ? $this->date('date_to') : now();

            if ($from->isAfter($to)) {
                $validator->errors()->add('date_from', __('The start date must be on or before the end date.'));

                return;
            }

            if ($from->diffInDays($to) > (int) config('reports.max_range_days', 366)) {
                $validator->errors()->add('date_from', __(
                    'The date window cannot exceed :days days.',
                    ['days' => (int) config('reports.max_range_days', 366)],
                ));
            }
        });
    }
}
