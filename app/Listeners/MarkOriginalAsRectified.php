<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Shared\Contracts\Clock;
use App\Events\InvoiceIssued;

/**
 * Al emitir una rectificativa, la factura que corrige pasa a "rectificada" y lo
 * apunta en su historial. Con una segunda rectificativa la original ya está
 * rectificada: conserva la primera fecha y solo suma el evento.
 *
 * Síncrono, dentro de la transacción de emisión: si falla, la rectificativa
 * tampoco queda emitida.
 */
final readonly class MarkOriginalAsRectified
{
    public function __construct(private Clock $clock) {}

    public function handle(InvoiceIssued $event): void
    {
        $creditNote = CreditNote::query()->find($event->documentId);

        if ($creditNote === null || $creditNote->rectifies_id === null) {
            return;
        }

        $original = Document::query()->lockForUpdate()->findOrFail($creditNote->rectifies_id);

        if ($original->status instanceof DocumentStatus && $original->status->allows(DocumentStatus::Rectified)) {
            $original->status = DocumentStatus::Rectified;
        }

        $original->rectified_at ??= $this->clock->now();
        $original->save();

        $original->events()->create([
            'event' => DocumentEventType::Rectified,
            'payload' => [
                'credit_note_id' => $creditNote->id,
                'full_number' => $creditNote->full_number,
                'rectification_type' => $creditNote->rectification_type?->value,
            ],
        ]);
    }
}
