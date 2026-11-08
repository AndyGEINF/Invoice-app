<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\DraftData;
use App\Application\Documents\Data\LineData;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Documents\Exceptions\CannotRectifyInvoice;
use App\Domain\Documents\Invoice;
use App\Listeners\MarkOriginalAsRectified;
use Illuminate\Support\Facades\DB;

/**
 * Crea una rectificativa en borrador a partir de una factura emitida, enlazada
 * con `rectifies_id`, con el tipo y el motivo. La original no cambia hasta que la
 * rectificativa se emite ({@see MarkOriginalAsRectified}).
 *
 * Las líneas se copian como punto de partida:
 * - Por sustitución, tal cual: la rectificativa recoge la factura correcta
 *   completa, y el usuario cambia lo que estaba mal.
 * - Por diferencias, con la cantidad en negativo: de partida anula la factura
 *   entera, y el usuario deja solo lo que hay que devolver (o lo cambia a
 *   positivo si hay que cobrar más). El precio siempre queda en positivo.
 *
 * Una factura puede tener varias rectificativas.
 */
final readonly class CreateRectification
{
    public function __construct(private DraftWriter $writer) {}

    public function __invoke(Invoice $invoice, RectificationType $type, string $reason): CreditNote
    {
        if (! $invoice->isIssued()) {
            throw CannotRectifyInvoice::draft();
        }

        return DB::transaction(function () use ($invoice, $type, $reason): CreditNote {
            $data = DocumentCopy::draftDataFrom($invoice);

            if ($type === RectificationType::Differences) {
                $data = $this->negated($data);
            }

            $creditNote = new CreditNote;
            // Van antes de guardar: la base exige que una rectificativa diga a quién corrige y cómo.
            $creditNote->forceFill([
                'rectifies_id' => $invoice->id,
                'rectification_type' => $type,
                'rectification_reason' => trim($reason),
            ]);

            $this->writer->write($creditNote, $data);

            $creditNote->events()->create([
                'event' => DocumentEventType::Created,
                'payload' => ['rectifies' => $invoice->full_number, 'rectification_type' => $type->value],
            ]);

            return $creditNote;
        });
    }

    private function negated(DraftData $data): DraftData
    {
        return new DraftData(
            customerId: $data->customerId,
            seriesId: $data->seriesId,
            lines: array_map(static fn (LineData $line): LineData => new LineData(
                position: $line->position,
                description: $line->description,
                quantity: $line->quantity->negate(),
                unitPrice: $line->unitPrice,
                discount: $line->discount,
                vatRate: $line->vatRate,
                surchargeRate: $line->surchargeRate,
                irpfApplies: $line->irpfApplies,
                exemptionCode: $line->exemptionCode,
                unit: $line->unit,
                productId: $line->productId,
            ), $data->lines),
            currency: $data->currency,
            globalDiscount: $data->globalDiscount,
            irpfRate: $data->irpfRate,
            notes: $data->notes,
        );
    }
}
