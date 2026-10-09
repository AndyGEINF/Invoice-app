<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\DraftData;
use App\Application\Documents\Data\LineData;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;

/**
 * Contenido de un documento listo para crear otro a partir de él (convertir un
 * presupuesto en factura, duplicar): cliente, líneas, descuento, retención y
 * notas. Nunca se copian número, fechas, serie ni snapshots: el documento nuevo
 * es un borrador que tomará los suyos.
 */
final readonly class DocumentCopy
{
    public static function draftDataFrom(Document $source): DraftData
    {
        return new DraftData(
            customerId: $source->customer_id,
            seriesId: null,
            lines: $source->lines()
                ->orderBy('position')
                ->get()
                ->map(static fn (DocumentLine $line): LineData => new LineData(
                    position: $line->position,
                    description: $line->description,
                    quantity: $line->quantity,
                    unitPrice: $line->unit_price,
                    discount: $line->discount_percent,
                    vatRate: $line->vat_rate,
                    surchargeRate: $line->surcharge_rate,
                    irpfApplies: $line->irpf_applies,
                    exemptionCode: $line->exemption_code,
                    unit: $line->unit,
                    productId: $line->product_id,
                ))
                ->values()
                ->all(),
            currency: $source->currency,
            globalDiscount: $source->global_discount_percent,
            irpfRate: $source->irpf_rate,
            notes: $source->notes,
        );
    }
}
