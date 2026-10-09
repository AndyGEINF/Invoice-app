<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Exceptions\DocumentHasNoLines;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Exceptions\QuoteAlreadyConverted;
use App\Domain\Documents\Exceptions\QuoteExpired;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Documents\SeriesNumberAllocator;
use App\Domain\Documents\SnapshotFactory;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mueve un presupuesto por su ciclo (data-model §7):
 * `draft → sent → accepted | rejected`. La conversión a factura tiene su propio
 * caso de uso ({@see ConvertQuoteToInvoice}).
 *
 * Al enviar recibe número de la serie de presupuestos, con la misma serie
 * bloqueada que la numeración de facturas, y congela emisor y cliente. Un
 * presupuesto caducado no se puede aceptar.
 */
final readonly class TransitionQuote
{
    public function __construct(
        private RecalculateDocument $recalculate,
        private SeriesNumberAllocator $allocator,
        private SnapshotFactory $snapshots,
        private Clock $clock,
    ) {}

    public function __invoke(Quote $quote, QuoteStatus $target): Quote
    {
        if ($target === QuoteStatus::Converted) {
            throw new InvalidArgumentException('Para convertir un presupuesto en factura usa ConvertQuoteToInvoice.');
        }

        return DB::transaction(function () use ($quote, $target): Quote {
            /** @var Quote $quote */
            $quote = $quote->newQuery()->lockForUpdate()->findOrFail($quote->getKey());
            $label = $quote->full_number ?? 'borrador';

            if ($quote->isConverted()) {
                throw QuoteAlreadyConverted::for($label);
            }

            if (! $quote->status->allows($target)) {
                throw DocumentIsImmutable::invalidTransition($label, $quote->status->label(), $target->label());
            }

            match ($target) {
                QuoteStatus::Sent => $this->send($quote),
                QuoteStatus::Accepted => $this->accept($quote),
                default => $quote->status = $target,
            };

            $quote->save();

            $quote->events()->create([
                'event' => match ($target) {
                    QuoteStatus::Sent => DocumentEventType::QuoteSent,
                    QuoteStatus::Accepted => DocumentEventType::QuoteAccepted,
                    default => DocumentEventType::QuoteRejected,
                },
                'payload' => ['full_number' => $quote->full_number],
            ]);

            return $quote;
        });
    }

    /** Número de la serie de presupuestos, fecha de hoy y datos congelados. */
    private function send(Quote $quote): void
    {
        $issuer = Issuer::current();
        $issuer->assertComplete();

        if (! $quote->lines()->exists()) {
            throw DocumentHasNoLines::cannotSend();
        }

        ($this->recalculate)($quote);

        $today = $this->clock->today();

        /** @var Series $series */
        $series = Series::query()
            ->forType($quote->type)
            ->whereKey($quote->series_id ?? Series::defaultFor($quote->type)?->id)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $this->allocator->allocate($series, $today);
        $this->snapshots->freezeInto($quote, $issuer, $quote->customer);

        $quote->forceFill([
            'series_id' => $series->id,
            'fiscal_year' => $series->fiscalYearFor($today->year),
            'number' => $number->number,
            'full_number' => $number->full(),
            'issue_date' => $today,
            'valid_until' => $quote->valid_until ?? $today->addDays((int) config('invoice.quote.default_validity_days')),
            'status' => QuoteStatus::Sent,
        ]);
    }

    private function accept(Quote $quote): void
    {
        if ($quote->isExpired($this->clock->today()) && $quote->valid_until !== null) {
            throw QuoteExpired::cannotAccept($quote->full_number ?? 'borrador', $quote->valid_until);
        }

        $quote->status = QuoteStatus::Accepted;
    }
}
