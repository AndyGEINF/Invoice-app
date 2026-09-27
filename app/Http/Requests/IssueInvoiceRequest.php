<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos opcionales al emitir: serie, fecha de expedición y fecha de operación.
 * Sin ellos se usa la serie del borrador (o la de por defecto) y la fecha de hoy.
 */
final class IssueInvoiceRequest extends FormRequest
{
    private const string DATE_RULE = 'date_format:Y-m-d';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'series_id' => ['nullable', 'uuid', Rule::exists('series', 'id')],
            'issue_date' => ['nullable', self::DATE_RULE],
            'operation_date' => ['nullable', self::DATE_RULE],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'series_id' => 'serie',
            'issue_date' => 'fecha de expedición',
            'operation_date' => 'fecha de operación',
        ];
    }

    public function seriesId(): ?string
    {
        return $this->validated('series_id');
    }

    public function issueDate(): ?CarbonImmutable
    {
        return $this->dateOrNull('issue_date');
    }

    public function operationDate(): ?CarbonImmutable
    {
        return $this->dateOrNull('operation_date');
    }

    private function dateOrNull(string $key): ?CarbonImmutable
    {
        $value = $this->validated($key);

        return $value === null ? null : CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }
}
