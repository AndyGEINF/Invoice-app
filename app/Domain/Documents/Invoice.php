<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Concerns\IsFiscalDocument;
use App\Domain\Documents\Concerns\IsTypedDocument;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * Factura.
 *
 * Documento fiscal: al emitirse recibe número y queda inmutable. Corregirla es
 * emitir una rectificativa (decisión D6). No usa SoftDeletes: una factura
 * emitida nunca se borra, ni siquiera de forma lógica.
 *
 * @property DocumentStatus $status
 */
#[UseFactory(InvoiceFactory::class)]
final class Invoice extends Document
{
    use IsFiscalDocument;
    use IsTypedDocument;

    public static function fixedType(): DocumentType
    {
        return DocumentType::Invoice;
    }
}
