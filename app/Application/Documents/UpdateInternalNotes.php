<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Document;

/**
 * Notas internas de un documento: lo que apunta el usuario y el cliente nunca
 * ve (no se imprimen). Se pueden cambiar también en un documento emitido:
 * `internal_notes` está entre las columnas que la inmutabilidad permite
 * ({@see Document::MUTABLE_AFTER_ISSUE}).
 */
final readonly class UpdateInternalNotes
{
    public function __invoke(Document $document, ?string $notes): void
    {
        $notes = $notes === null || trim($notes) === '' ? null : $notes;

        if ($document->internal_notes === $notes) {
            return;
        }

        $document->internal_notes = $notes;
        $document->save();
    }
}
