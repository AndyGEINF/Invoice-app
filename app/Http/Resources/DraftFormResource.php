<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Shared\Percentage;
use App\Http\Resources\Concerns\PresentsDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Borrador en la forma del formulario (`DraftForm` de contracts/web-routes.md),
 * con los decimales como texto para editarlos sin pasar por float, más el
 * desglose que calculó el servidor al guardar.
 *
 * @property Document $resource
 */
final class DraftFormResource extends JsonResource
{
    use PresentsDocuments;

    /**
     * Formulario vacío de un documento nuevo.
     *
     * @return array<string, mixed>
     */
    public static function blank(string $currency, Percentage $irpfRate, ?string $customerId = null): array
    {
        return [
            'id' => null,
            'version' => null,
            'customer_id' => $customerId,
            'series_id' => null,
            'issue_date' => null,
            'valid_until' => null,
            'due_date' => null,
            'operation_date' => null,
            'currency' => $currency,
            'global_discount_percent' => (string) Percentage::zero(),
            'irpf_rate' => (string) $irpfRate,
            'notes' => null,
            'lines' => [],
            'breakdown' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'id' => $document->id,
            // Cambia en cada guardado: el editor se reinicia con lo que calculó el servidor.
            'version' => $document->updated_at?->toIso8601String(),
            'customer_id' => $document->customer_id,
            'series_id' => $document->series_id,
            'issue_date' => self::date($document->issue_date),
            'valid_until' => self::date($document->valid_until),
            'due_date' => self::date($document->due_date),
            'operation_date' => self::date($document->operation_date),
            'currency' => $document->currency->value,
            'global_discount_percent' => (string) $document->global_discount_percent,
            'irpf_rate' => (string) $document->irpf_rate,
            'notes' => $document->notes,
            'lines' => $document->lines
                ->map(static fn (DocumentLine $line): array => [
                    'id' => $line->id,
                    'position' => $line->position,
                    'product_id' => $line->product_id,
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
                ])
                ->all(),
            'breakdown' => self::breakdown($document),
        ];
    }
}
