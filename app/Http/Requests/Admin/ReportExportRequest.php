<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Async export request (FR-RPT-004/005, NFR-PERF-005): report type plus
 * the export window. The job streams the file on the reports queue so
 * the API answers 202 immediately.
 */
class ReportExportRequest extends FormRequest
{
    /**
     * Route middleware enforces reports.export.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string|list<mixed>>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', Rule::in(array_map(
                fn (ReportType $type): string => $type->value,
                ReportType::cases()
            ))],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to', 'after_or_equal:2000-01-01'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * The report type as an enum, safe to call after validation passes.
     */
    public function reportType(): ReportType
    {
        return ReportType::from((string) $this->input('report_type'));
    }

    /**
     * Enforce the maximum window on the exported range.
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
                $validator->errors()->add('date_from', __('The date window cannot exceed :days days.', [
                    'days' => (int) config('reports.max_range_days', 366),
                ]));
            }
        });
    }
}
