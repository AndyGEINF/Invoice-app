<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Documents\DocumentEvent;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Events\InvoiceIssued;

/**
 * Apunta en el historial del documento lo que le ha pasado.
 *
 * Es síncrono a propósito: si falla, la emisión entera se deshace y no queda
 * una factura emitida sin rastro en su historial.
 */
final class RecordDocumentEvent
{
    public function handle(InvoiceIssued $event): void
    {
        DocumentEvent::query()->create([
            'document_id' => $event->documentId,
            'event' => DocumentEventType::Issued,
            'payload' => $event->details,
        ]);
    }
}
