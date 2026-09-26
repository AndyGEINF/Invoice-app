<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Exceptions\DocumentHasNoLines;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Exceptions\IssueDateBeforeSeriesLast;
use App\Domain\Documents\Exceptions\IssueDateInFuture;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\InvoiceTypeResolver;
use App\Domain\Documents\Series;
use App\Domain\Documents\SeriesNumberAllocator;
use App\Domain\Documents\SnapshotFactory;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Events\InvoiceIssued;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Emite una factura o rectificativa en borrador: le da número, congela emisor
 * y cliente y la deja inmutable.
 *
 * Todo ocurre en una transacción con la serie bloqueada (decisión D2): dos
 * emisiones simultáneas se esperan, nunca comparten número y, si algo falla,
 * no se consume ninguno.
 */
final readonly class IssueInvoice
{
    public function __construct(
        private RecalculateDocument $recalculate,
        private SeriesNumberAllocator $allocator,
        private SnapshotFactory $snapshots,
        private InvoiceTypeResolver $typeResolver,
        private Clock $clock,
    ) {}

    public function __invoke(
        Document $document,
        ?string $seriesId = null,
        ?CarbonImmutable $issueDate = null,
        ?CarbonImmutable $operationDate = null,
    ): IssueResult {
        if (! $document instanceof Invoice && ! $document instanceof CreditNote) {
            throw new InvalidArgumentException('Solo se emiten facturas y rectificativas.');
        }

        $issuer = Issuer::current();
        $issuer->assertComplete();

        return DB::transaction(function () use ($document, $issuer, $seriesId, $issueDate, $operationDate): IssueResult {
            /** @var Invoice|CreditNote $document */
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if ($document->status !== DocumentStatus::Draft) {
                throw DocumentIsImmutable::alreadyIssued($document->full_number ?? $document->id);
            }

            if (! $document->lines()->exists()) {
                throw DocumentHasNoLines::cannotIssue();
            }

            // Totales frescos: el motor comprueba además las causas de exención.
            ($this->recalculate)($document);

            $today = $this->clock->today();
            $issueDate = ($issueDate ?? $today)->startOfDay();

            if ($issueDate->greaterThan($today)) {
                throw IssueDateInFuture::for($issueDate, $today);
            }

            $series = $this->lockSeries($document, $seriesId);
            $this->assertAfterSeriesLast($series, $issueDate);

            $number = $this->allocator->allocate($series, $issueDate);
            $customer = $document->customer;

            $this->snapshots->freezeInto($document, $issuer, $customer);

            $document->forceFill([
                'series_id' => $series->id,
                'fiscal_year' => $series->fiscalYearFor($issueDate->year),
                'number' => $number->number,
                'full_number' => $number->full(),
                'issue_date' => $issueDate,
                'operation_date' => $operationDate ?? $document->operation_date,
                'due_date' => $document->due_date ?? $issueDate->addDays(
                    $customer?->payment_terms_days ?? (int) config('invoice.invoice.default_payment_terms_days')
                ),
                'invoice_type' => $this->typeResolver->resolve($document),
                'status' => DocumentStatus::Issued,
                'issued_at' => $this->clock->now(),
            ])->save();

            InvoiceIssued::dispatch($document->id, [
                'full_number' => $document->full_number,
                'invoice_type' => $document->invoice_type->value,
                'total_cents' => $document->total->cents,
            ]);

            return new IssueResult($document, $this->warningsFor($document));
        });
    }

    /** La serie elegida, la que ya tenía el borrador o la de por defecto, bloqueada. */
    private function lockSeries(Document $document, ?string $seriesId): Series
    {
        $seriesId ??= $document->series_id ?? Series::defaultFor($document->type)?->id;

        /** @var Series $series */
        $series = Series::query()
            ->forType($document->type)
            ->whereKey($seriesId)
            ->lockForUpdate()
            ->firstOrFail();

        return $series;
    }

    /** Se comprueba con la serie ya bloqueada: nadie puede emitir en medio. */
    private function assertAfterSeriesLast(Series $series, CarbonImmutable $issueDate): void
    {
        $last = $series->documents()->whereNotNull('number')->max('issue_date');

        if ($last === null) {
            return;
        }

        $lastIssueDate = CarbonImmutable::parse($last)->startOfDay();

        if ($issueDate->lessThan($lastIssueDate)) {
            throw IssueDateBeforeSeriesLast::for($series->code, $issueDate, $lastIssueDate);
        }
    }

    /** @return list<string> */
    private function warningsFor(Document $document): array
    {
        $warnings = [];

        if ($document->invoice_type?->isSimplified()
            && $document->total->cents > (int) config('invoice.invoice.simplified_limit_cents')) {
            $warnings[] = IssueResult::WARNING_SIMPLIFIED_OVER_LIMIT;
        }

        return $warnings;
    }
}
