<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Documents\Data\DraftData;
use App\Application\Documents\DraftWriter;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Currency;
use App\Domain\Shared\Percentage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Valida el `DraftForm` de contracts/web-routes.md: formatos, rangos y tipos
 * impositivos admitidos por config/invoice.php.
 *
 * Las reglas que dependen del documento (cantidades negativas solo en
 * rectificativas, exención obligatoria, cliente archivado…) las aplica el caso
 * de uso ({@see DraftWriter}).
 */
final class DraftDocumentRequest extends FormRequest
{
    public const int MAX_LINES = 500;

    public const int MAX_DESCRIPTION_LENGTH = 2000;

    public const int MAX_UNIT_LENGTH = 20;

    public const int MAX_NOTES_LENGTH = 5000;

    /** Cantidad con signo y hasta 4 decimales (el signo lo decide el tipo de documento). */
    private const string QUANTITY_PATTERN = '/^-?\d{1,8}(\.\d{1,4})?$/';

    /** Precio unitario en euros con hasta 3 decimales (milésimas). */
    private const string UNIT_PRICE_PATTERN = '/^\d{1,10}(\.\d{1,3})?$/';

    private const string PERCENTAGE_RULE = 'decimal:0,2';

    private const string DATE_RULE = 'date_format:Y-m-d';

    /** Campos de porcentaje que se normalizan a dos decimales ("21" → "21.00"). */
    private const array LINE_PERCENTAGES = ['discount_percent', 'vat_rate', 'surcharge_rate'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')],
            'series_id' => ['nullable', 'uuid', Rule::exists('series', 'id')],
            'issue_date' => ['nullable', self::DATE_RULE],
            'valid_until' => ['nullable', self::DATE_RULE, 'after_or_equal:issue_date'],
            'due_date' => ['nullable', self::DATE_RULE],
            'operation_date' => ['nullable', self::DATE_RULE],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            'global_discount_percent' => ['sometimes', self::PERCENTAGE_RULE, 'between:'.Percentage::MIN.','.Percentage::MAX],
            'irpf_rate' => ['sometimes', self::PERCENTAGE_RULE, 'between:'.Percentage::MIN.','.Percentage::MAX],
            'notes' => ['nullable', 'string', 'max:'.self::MAX_NOTES_LENGTH],

            'lines' => ['present', 'array', 'max:'.self::MAX_LINES],
            'lines.*.id' => ['nullable', 'uuid'],
            'lines.*.position' => ['required', 'integer', 'min:1', 'distinct'],
            'lines.*.product_id' => ['nullable', 'uuid', Rule::exists('products', 'id')],
            'lines.*.description' => ['required_without:lines.*.product_id', 'nullable', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            'lines.*.quantity' => ['required', 'regex:'.self::QUANTITY_PATTERN],
            'lines.*.unit' => ['nullable', 'string', 'max:'.self::MAX_UNIT_LENGTH],
            'lines.*.unit_price' => ['required_without:lines.*.product_id', 'nullable', 'regex:'.self::UNIT_PRICE_PATTERN],
            'lines.*.discount_percent' => ['sometimes', self::PERCENTAGE_RULE, 'between:'.Percentage::MIN.','.Percentage::MAX],
            'lines.*.vat_rate' => ['required_without:lines.*.product_id', 'nullable', Rule::in(config('invoice.tax.vat_rates'))],
            'lines.*.surcharge_rate' => ['sometimes', Rule::in(config('invoice.tax.surcharge_rates'))],
            'lines.*.irpf_applies' => ['sometimes', 'boolean'],
            'lines.*.exemption_code' => ['nullable', Rule::enum(ExemptionCode::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'cliente',
            'series_id' => 'serie',
            'issue_date' => 'fecha',
            'valid_until' => 'válido hasta',
            'due_date' => 'vencimiento',
            'operation_date' => 'fecha de operación',
            'global_discount_percent' => 'descuento global',
            'irpf_rate' => 'retención de IRPF',
            'lines' => 'líneas',
            'lines.*.description' => 'descripción',
            'lines.*.quantity' => 'cantidad',
            'lines.*.unit_price' => 'precio',
            'lines.*.discount_percent' => 'descuento',
            'lines.*.vat_rate' => 'IVA',
            'lines.*.surcharge_rate' => 'recargo de equivalencia',
            'lines.*.exemption_code' => 'causa de exención',
        ];
    }

    public function toDraftData(): DraftData
    {
        return DraftData::fromArray($this->validated());
    }

    /**
     * Los porcentajes se comparan como texto con la lista de config: "21" y
     * "21.0" se normalizan a "21.00" antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_map(function (mixed $line): mixed {
                if (! is_array($line)) {
                    return $line;
                }

                foreach (self::LINE_PERCENTAGES as $field) {
                    if (isset($line[$field])) {
                        $line[$field] = self::normalizePercentage($line[$field]);
                    }
                }

                return $line;
            }, $lines),
        ]);
    }

    /** Si no es un porcentaje válido se deja tal cual para que lo rechacen las reglas. */
    private static function normalizePercentage(mixed $value): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        try {
            return (string) Percentage::of((string) $value);
        } catch (Throwable) {
            return $value;
        }
    }
}
