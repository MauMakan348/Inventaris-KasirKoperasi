<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', $this->dateRangeRule()],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'from.required_with' => 'Tanggal awal dan akhir harus diisi bersamaan.',
            'to.required_with' => 'Tanggal awal dan akhir harus diisi bersamaan.',
        ];
    }

    /**
     * Reject an inverted range or a range longer than 366 days.
     */
    private function dateRangeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! is_string($from) || ! is_string($to)) {
                return;
            }

            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');

            if ($start === false || $end === false) {
                return;
            }

            if ($start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
                return;
            }

            if ($start->greaterThan($end)) {
                $fail('Tanggal awal tidak boleh melebihi tanggal akhir.');

                return;
            }

            if ($start->diffInDays($end) > 366) {
                $fail('Rentang laporan maksimal 366 hari.');
            }
        };
    }
}
