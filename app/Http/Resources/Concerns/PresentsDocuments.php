<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentTax;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;

/**
 * Formato común de los documentos hacia el frontend (contracts/web-routes.md):
 * importes en céntimos más su texto formateado, fechas `Y-m-d` y el desglose
 * tal como lo guardó el servidor. El frontend nunca calcula.
 */
trait PresentsDocuments
{
    /** Claves de los totales del documento, en el orden en que se muestran. */
    private const array TOTAL_KEYS = ['taxable_base', 'vat_total', 'surcharge_total', 'irpf_total', 'total'];

    /**
     * `['total' => 19250, 'total_formatted' => '192,50 €']`
     *
     * @return array<string, int|string>
     */
    protected static function amount(string $key, Money $money): array
    {
        return [$key => $money->cents, "{$key}_formatted" => $money->format()];
    }

    protected static function date(?CarbonImmutable $date): ?string
    {
        return $date?->toDateString();
    }

    /** Estado de cobro derivado; null en presupuestos y borradores. */
    protected static function paymentStatusOf(Document $document): ?PaymentStatus
    {
        if (! $document instanceof Invoice && ! $document instanceof CreditNote) {
            return null;
        }

        return $document->paymentStatus(app(Clock::class)->today());
    }

    /** Nombre del cliente: el congelado si está emitido, si no el de la ficha. */
    protected static function customerName(Document $document): ?string
    {
        return $document->customer_snapshot['legal_name']
            ?? $document->customer?->legal_name;
    }

    /**
     * Desglose de impuestos y totales guardados (`document_taxes`).
     *
     * @return array<string, mixed>
     */
    protected static function breakdown(Document $document): array
    {
        $totals = [];
        $formatted = [];

        foreach (self::TOTAL_KEYS as $key) {
            /** @var Money $money */
            $money = $document->{$key};
            $totals[$key] = $money->cents;
            $formatted[$key] = $money->format();
        }

        return [
            'taxes' => $document->taxes
                ->sortBy(static fn (DocumentTax $tax): string => $tax->tax_type->value.'-'.$tax->rate)
                ->values()
                ->map(static fn (DocumentTax $tax): array => [
                    'tax_type' => $tax->tax_type->value,
                    'tax_type_label' => $tax->tax_type->label(),
                    'rate' => (string) $tax->rate,
                    'exemption_code' => $tax->exemption_code?->value,
                    ...self::amount('base', $tax->base),
                    ...self::amount('amount', $tax->amount),
                ])
                ->all(),
            ...$totals,
            'totals_formatted' => $formatted,
        ];
    }
}
