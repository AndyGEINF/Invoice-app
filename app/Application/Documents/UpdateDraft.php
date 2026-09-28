<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\DraftData;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Guarda los cambios de un borrador (o de un presupuesto no convertido) y
 * recalcula su desglose. Una factura emitida no se edita: se rectifica.
 */
final readonly class UpdateDraft
{
    public function __construct(private DraftWriter $writer) {}

    public function __invoke(Document $document, DraftData $data): Document
    {
        return DB::transaction(function () use ($document, $data): Document {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if (! $document->isEditable()) {
                throw DocumentIsImmutable::notEditable($document);
            }

            $this->writer->write($document, $data);

            $document->events()->create([
                'event' => DocumentEventType::Updated,
                'payload' => ['lines' => count($data->lines)],
            ]);

            return $document;
        });
    }
}
