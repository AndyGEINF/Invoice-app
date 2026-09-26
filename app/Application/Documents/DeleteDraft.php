<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Borra un borrador (o un presupuesto no convertido) con sus líneas y su
 * desglose. Una factura emitida no se borra nunca: se anula con una
 * rectificativa.
 *
 * El historial del documento se borra con él, así que el evento `deleted` queda
 * en el log de la aplicación.
 */
final readonly class DeleteDraft
{
    public function __invoke(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if (! $document->isEditable()) {
                throw $document->type === DocumentType::Quote
                    ? DocumentIsImmutable::quoteConverted($document->full_number ?? $document->id)
                    : DocumentIsImmutable::cannotDelete($document->full_number ?? $document->id);
            }

            $document->delete();

            Log::info(DocumentEventType::Deleted->label(), [
                'event' => DocumentEventType::Deleted->value,
                'document_id' => $document->id,
                'type' => $document->type->value,
                'full_number' => $document->full_number,
                'total_cents' => $document->total->cents,
            ]);
        });
    }
}
