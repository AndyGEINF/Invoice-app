<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;

/**
 * Inmutabilidad en el modelo: segunda barrera junto al trigger de PostgreSQL
 * (T028). El trigger protege frente a SQL crudo; esta da mensajes claros a la
 * interfaz antes de llegar a la base de datos.
 *
 * Se decide con el estado ORIGINAL del documento: el paso de borrador a emitida
 * se guarda en la misma operación y debe permitirse.
 */
final class DocumentObserver
{
    public function updating(Document $document): void
    {
        $type = $this->typeOf($document);
        $originalStatus = (string) $document->getRawOriginal('status');

        if ($type === DocumentType::Quote) {
            if ($originalStatus === QuoteStatus::Converted->value) {
                throw DocumentIsImmutable::quoteConverted($this->label($document));
            }

            return;
        }

        if ($originalStatus === DocumentStatus::Draft->value) {
            return;
        }

        $forbidden = array_values(array_diff(
            array_keys($document->getDirty()),
            Document::MUTABLE_AFTER_ISSUE,
        ));

        if ($forbidden !== []) {
            throw DocumentIsImmutable::cannotChange($this->label($document), $forbidden);
        }

        if ($document->isDirty('status')) {
            $from = DocumentStatus::from($originalStatus);
            $to = DocumentStatus::from((string) $document->getAttributes()['status']);

            if (! $from->allows($to)) {
                throw DocumentIsImmutable::invalidTransition($this->label($document), $from->label(), $to->label());
            }
        }
    }

    public function deleting(Document $document): void
    {
        $type = $this->typeOf($document);
        $originalStatus = (string) $document->getRawOriginal('status');

        if ($type === DocumentType::Quote) {
            if ($originalStatus === QuoteStatus::Converted->value) {
                throw DocumentIsImmutable::quoteConverted($this->label($document));
            }

            return;
        }

        if ($originalStatus !== DocumentStatus::Draft->value) {
            throw DocumentIsImmutable::cannotDelete($this->label($document));
        }
    }

    private function typeOf(Document $document): DocumentType
    {
        return DocumentType::from((string) ($document->getRawOriginal('type') ?? $document->getAttributes()['type']));
    }

    private function label(Document $document): string
    {
        return $document->getRawOriginal('full_number') ?? $document->getKey() ?? 'sin guardar';
    }
}
