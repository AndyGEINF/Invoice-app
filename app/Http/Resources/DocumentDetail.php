<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Quote;
use App\Domain\Shared\Contracts\Clock;
use App\Http\Resources\Concerns\PresentsDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Documento completo para la página de detalle (`DocumentDetail` de
 * contracts/web-routes.md). Cabecera, snapshots, líneas, desglose y totales.
 *
 * @property Document $resource
 */
final class DocumentDetail extends JsonResource
{
    use PresentsDocuments;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $document = $this->resource;
        $paymentStatus = self::paymentStatusOf($document);
        $customer = $document->customer;

        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'payment_status' => $paymentStatus?->value,
            'payment_status_label' => $paymentStatus?->label(),
            'is_expired' => $document instanceof Quote ? $document->isExpired(app(Clock::class)->today()) : null,
            'paid_at' => self::date($document->paid_at),
            'paid_note' => $document->paid_note,
            'full_number' => $document->full_number,
            'series_code' => $document->series?->code,
            'issue_date' => self::date($document->issue_date),
            'operation_date' => self::date($document->operation_date),
            'due_date' => self::date($document->due_date),
            'valid_until' => self::date($document->valid_until),
            'invoice_type' => $document->invoice_type?->value,
            'invoice_type_label' => $document->invoice_type?->label(),
            'customer' => $customer === null ? null : [
                'id' => $customer->id,
                'legal_name' => $customer->legal_name,
                'tax_id' => $customer->tax_id,
            ],
            'issuer_snapshot' => $document->issuer_snapshot,
            'customer_snapshot' => $document->customer_snapshot,
            'lines' => $document->lines
                ->map(fn (DocumentLine $line): array => self::lineView($line))
                ->all(),
            ...self::breakdown($document),
            'global_discount_percent' => (string) $document->global_discount_percent,
            'irpf_rate' => (string) $document->irpf_rate,
            'currency' => $document->currency->value,
            'notes' => $document->notes,
            'internal_notes' => $document->internal_notes,
            'issued_at' => $document->issued_at?->toIso8601String(),
            'pdf_available' => $document->pdf_path !== null,
        ];
    }

    /**
     * Línea tal como se muestra (`LineView`).
     *
     * @return array<string, mixed>
     */
    private static function lineView(DocumentLine $line): array
    {
        return [
            'id' => $line->id,
            'position' => $line->position,
            'description' => $line->description,
            'quantity' => (string) $line->quantity,
            'unit' => $line->unit,
            'unit_price' => $line->unit_price->toDecimalString(),
            'discount_percent' => (string) $line->discount_percent,
            'vat_rate' => (string) $line->vat_rate,
            'surcharge_rate' => (string) $line->surcharge_rate,
            'irpf_applies' => $line->irpf_applies,
            'exemption_code' => $line->exemption_code?->value,
            ...self::amount('line_base', $line->line_base),
        ];
    }
}
