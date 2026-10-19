<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Quita el marcador de cobro (se marcó por error o el cobro se devolvió). La
 * factura vuelve a pendiente o vencida según su vencimiento. Si no estaba
 * marcada, no hace nada.
 */
final readonly class UnmarkPaid
{
    public function __invoke(Invoice|CreditNote $document): Invoice|CreditNote
    {
        return DB::transaction(function () use ($document): Invoice|CreditNote {
            /** @var Invoice|CreditNote $document */
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if ($document->paid_at === null) {
                return $document;
            }

            $document->forceFill(['paid_at' => null, 'paid_note' => null])->save();

            $document->events()->create(['event' => DocumentEventType::UnmarkedPaid]);

            return $document;
        });
    }
}
