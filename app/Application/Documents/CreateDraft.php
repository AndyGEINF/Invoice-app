<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\DraftData;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Crea un presupuesto o una factura en borrador, sin número: el número se
 * asigna al emitir (decisión D2).
 *
 * Las rectificativas no se crean aquí: nacen de la factura que corrigen.
 */
final readonly class CreateDraft
{
    public function __construct(private DraftWriter $writer) {}

    public function __invoke(DocumentType $type, DraftData $data): Document
    {
        $document = match ($type) {
            DocumentType::Quote => new Quote,
            DocumentType::Invoice => new Invoice,
            DocumentType::CreditNote => throw new InvalidArgumentException(
                'Las rectificativas se crean desde la factura que corrigen.'
            ),
        };

        return DB::transaction(function () use ($document, $data): Document {
            $this->writer->write($document, $data);

            $document->events()->create([
                'event' => DocumentEventType::Created,
                'payload' => ['lines' => count($data->lines)],
            ]);

            return $document;
        });
    }
}
