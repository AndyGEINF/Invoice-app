<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Concerns\IsFiscalDocument;
use App\Domain\Documents\Concerns\IsTypedDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * Factura rectificativa.
 *
 * Siempre apunta a la factura que corrige y dice cómo (sustitución o
 * diferencias). Se numera en su propia serie y, al emitirse, la original pasa a
 * "rectificada".
 *
 * @property DocumentStatus $status
 */
#[UseFactory(CreditNoteFactory::class)]
final class CreditNote extends Document
{
    use IsFiscalDocument;
    use IsTypedDocument;

    public static function fixedType(): DocumentType
    {
        return DocumentType::CreditNote;
    }
}
