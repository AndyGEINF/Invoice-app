<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use InvalidArgumentException;

/**
 * Crea un borrador nuevo del mismo tipo con el contenido de otro documento:
 * para volver a enviar un presupuesto caducado o repetir una factura habitual.
 * Las fechas, el número y los datos congelados no se copian.
 *
 * Las rectificativas no se duplican: cada una corrige una factura concreta.
 */
final readonly class DuplicateDocument
{
    public function __construct(private CreateDraft $createDraft) {}

    public function __invoke(Document $source): Document
    {
        if ($source->type === DocumentType::CreditNote) {
            throw new InvalidArgumentException('Las rectificativas no se duplican: se crean desde la factura que corrigen.');
        }

        return ($this->createDraft)($source->type, DocumentCopy::draftDataFrom($source));
    }
}
