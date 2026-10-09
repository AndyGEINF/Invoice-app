<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Exceptions\QuoteAlreadyConverted;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un presupuesto aceptado en una factura en borrador con las mismas
 * líneas, cliente, descuento global y retención, enlazada con
 * `converted_from_id`. El presupuesto pasa a `converted` y deja de poder
 * editarse.
 *
 * La factura nace en borrador, sin número: se revisa y se emite como cualquier
 * otra.
 */
final readonly class ConvertQuoteToInvoice
{
    public function __construct(private CreateDraft $createDraft) {}

    public function __invoke(Quote $quote): Invoice
    {
        return DB::transaction(function () use ($quote): Invoice {
            /** @var Quote $quote */
            $quote = $quote->newQuery()->lockForUpdate()->findOrFail($quote->getKey());
            $label = $quote->full_number ?? 'borrador';

            if ($quote->isConverted()) {
                throw QuoteAlreadyConverted::for($label);
            }

            if ($quote->status !== QuoteStatus::Accepted) {
                throw DocumentIsImmutable::invalidTransition($label, $quote->status->label(), QuoteStatus::Converted->label());
            }

            /** @var Invoice $invoice */
            $invoice = ($this->createDraft)(DocumentType::Invoice, DocumentCopy::draftDataFrom($quote));
            $invoice->forceFill(['converted_from_id' => $quote->id])->save();

            $quote->status = QuoteStatus::Converted;
            $quote->save();

            $quote->events()->create([
                'event' => DocumentEventType::Converted,
                'payload' => ['invoice_id' => $invoice->id],
            ]);

            return $invoice;
        });
    }
}
