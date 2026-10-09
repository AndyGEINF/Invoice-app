<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\DraftData;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Quote;
use App\Domain\Documents\SnapshotFactory;
use App\Domain\Issuer\Issuer;
use Illuminate\Support\Facades\DB;

/**
 * Guarda los cambios de un borrador (o de un presupuesto no convertido) y
 * recalcula su desglose. Una factura emitida no se edita: se rectifica.
 */
final readonly class UpdateDraft
{
    public function __construct(
        private DraftWriter $writer,
        private SnapshotFactory $snapshots,
    ) {}

    public function __invoke(Document $document, DraftData $data): Document
    {
        return DB::transaction(function () use ($document, $data): Document {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if (! $document->isEditable()) {
                throw DocumentIsImmutable::notEditable($document);
            }

            $this->writer->write($document, $data);

            // Un presupuesto ya enviado vuelve a congelar emisor y cliente con lo
            // que hay ahora: lo que se reenvíe debe coincidir con lo guardado.
            if ($document instanceof Quote && $document->hasNumber()) {
                $this->snapshots->freezeInto($document, Issuer::current(), $document->customer()->first());
                $document->save();
            }

            $document->events()->create([
                'event' => DocumentEventType::Updated,
                'payload' => ['lines' => count($data->lines)],
            ]);

            return $document;
        });
    }
}
