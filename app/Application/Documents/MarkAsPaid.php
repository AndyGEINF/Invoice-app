<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Exceptions\CannotMarkAsPaid;
use App\Domain\Documents\Exceptions\NothingToCollect;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Marca una factura o rectificativa emitida como cobrada: solo una fecha y una
 * nota. La aplicación no gestiona pagos (sin importes ni formas de pago); el
 * estado `paid` / `unpaid` / `overdue` se sigue derivando de este marcador.
 *
 * `paid_at` y `paid_note` son de los pocos campos que pueden cambiar en un
 * documento emitido. Volver a marcarla actualiza la fecha y la nota.
 */
final readonly class MarkAsPaid
{
    public function __construct(private Clock $clock) {}

    public function __invoke(Invoice|CreditNote $document, CarbonImmutable $paidOn, ?string $note = null): Invoice|CreditNote
    {
        return DB::transaction(function () use ($document, $paidOn, $note): Invoice|CreditNote {
            /** @var Invoice|CreditNote $document */
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if (! $document->isIssued()) {
                throw CannotMarkAsPaid::draft();
            }

            if ($document->total->isZero()) {
                throw NothingToCollect::for((string) $document->full_number);
            }

            if ($paidOn->toDateString() > $this->clock->today()->toDateString()) {
                throw CannotMarkAsPaid::dateInFuture($paidOn);
            }

            $note = $note !== null && trim($note) !== '' ? trim($note) : null;

            $document->forceFill(['paid_at' => $paidOn, 'paid_note' => $note])->save();

            $document->events()->create([
                'event' => DocumentEventType::MarkedPaid,
                'payload' => ['paid_on' => $paidOn->toDateString(), 'note' => $note],
            ]);

            return $document;
        });
    }
}
