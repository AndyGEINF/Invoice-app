<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\DocumentTax;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;

/**
 * Las líneas y el desglose de un documento emitido (o de un presupuesto
 * convertido) no se crean, modifican ni borran.
 */
final class DocumentChildObserver
{
    public function creating(DocumentLine|DocumentTax $child): void
    {
        $this->guard($child);
    }

    public function updating(DocumentLine|DocumentTax $child): void
    {
        $this->guard($child);
    }

    public function deleting(DocumentLine|DocumentTax $child): void
    {
        $this->guard($child);
    }

    private function guard(DocumentLine|DocumentTax $child): void
    {
        /** @var array{type: string, status: string, full_number: ?string}|null $parent */
        $parent = Document::query()
            ->whereKey($child->document_id)
            ->toBase()
            ->first(['type', 'status', 'full_number']);

        // Documento ya borrado: es la cascada del borrado de un borrador.
        if ($parent === null) {
            return;
        }

        $type = DocumentType::from($parent->type);

        $locked = $type === DocumentType::Quote
            ? $parent->status === QuoteStatus::Converted->value
            : $parent->status !== DocumentStatus::Draft->value;

        if ($locked) {
            throw DocumentIsImmutable::cannotChangeLines($parent->full_number ?? $child->document_id);
        }
    }
}
