<?php

declare(strict_types=1);

use App\Application\Documents\ConvertQuoteToInvoice;
use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\DuplicateDocument;
use App\Application\Documents\TransitionQuote;
use App\Domain\Customers\Customer;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Exceptions\QuoteAlreadyConverted;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-10-11 10:00:00'));
    Series::factory()->quotes()->create();
    Series::factory()->invoices()->create();
    configuredIssuer();
});

/** Presupuesto con dos líneas, descuento global y retención, llevado hasta `$status`. */
function quoteIn(QuoteStatus $status): Quote
{
    $customer = Customer::factory()->create(['irpf_applies' => true]);

    /** @var Quote $quote */
    $quote = app(CreateDraft::class)(DocumentType::Quote, DraftData::fromArray([
        'customer_id' => $customer->id,
        'global_discount_percent' => '5',
        'irpf_rate' => '15',
        'notes' => 'Pago a 30 días por transferencia.',
        'lines' => [
            ['position' => 1, 'description' => 'Diseño web', 'quantity' => '1', 'unit_price' => '1500', 'vat_rate' => '21', 'irpf_applies' => true],
            ['position' => 2, 'description' => "Hosting anual\nIncluye dominio", 'quantity' => '2', 'unit' => 'año', 'unit_price' => '60.5', 'discount_percent' => '10', 'vat_rate' => '21'],
        ],
    ]));

    $transition = app(TransitionQuote::class);

    if ($status !== QuoteStatus::Draft) {
        $quote = $transition($quote, QuoteStatus::Sent);
    }

    if (in_array($status, [QuoteStatus::Accepted, QuoteStatus::Rejected], true)) {
        $quote = $transition($quote, $status);
    }

    return $quote;
}

it('crea una factura en borrador idéntica y enlazada', function () {
    $quote = quoteIn(QuoteStatus::Accepted);

    $invoice = app(ConvertQuoteToInvoice::class)($quote);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(DocumentStatus::Draft)
        ->and($invoice->full_number)->toBeNull()
        ->and($invoice->converted_from_id)->toBe($quote->id)
        ->and($invoice->customer_id)->toBe($quote->customer_id)
        ->and((string) $invoice->global_discount_percent)->toBe('5.00')
        ->and((string) $invoice->irpf_rate)->toBe('15.00')
        ->and($invoice->notes)->toBe('Pago a 30 días por transferencia.')
        ->and($invoice->total->cents)->toBe($quote->refresh()->total->cents);

    $lines = fn (Quote|Invoice $document): array => $document->lines()->orderBy('position')->get()
        ->map(fn (DocumentLine $line): array => [
            $line->position,
            $line->description,
            (string) $line->quantity,
            $line->unit,
            $line->unit_price->toDecimalString(),
            (string) $line->discount_percent,
            (string) $line->vat_rate,
            (string) $line->surcharge_rate,
            $line->irpf_applies,
            $line->line_base->cents,
        ])
        ->all();

    expect($lines($invoice))->toBe($lines($quote));
});

it('el presupuesto pasa a convertido y lo apunta en su historial', function () {
    $quote = quoteIn(QuoteStatus::Accepted);

    $invoice = app(ConvertQuoteToInvoice::class)($quote);

    $quote->refresh();
    $event = $quote->events()->where('event', DocumentEventType::Converted)->sole();
    expect($quote->status)->toBe(QuoteStatus::Converted)
        ->and($event->payload['invoice_id'])->toBe($invoice->id);
});

it('no se convierte dos veces', function () {
    $quote = quoteIn(QuoteStatus::Accepted);
    app(ConvertQuoteToInvoice::class)($quote);

    expect(fn () => app(ConvertQuoteToInvoice::class)($quote))->toThrow(QuoteAlreadyConverted::class);
    expect(Invoice::query()->where('converted_from_id', $quote->id)->count())->toBe(1);
});

it('solo se convierte un presupuesto aceptado', function (QuoteStatus $status) {
    $quote = quoteIn($status);

    expect(fn () => app(ConvertQuoteToInvoice::class)($quote))->toThrow(DocumentIsImmutable::class);
    expect(Invoice::query()->count())->toBe(0);
})->with([
    'borrador' => QuoteStatus::Draft,
    'enviado' => QuoteStatus::Sent,
    'rechazado' => QuoteStatus::Rejected,
]);

it('por la web: convertir abre la factura; un enviado sin aceptar da 422', function () {
    $sent = quoteIn(QuoteStatus::Sent);
    $this->postJson("/quotes/{$sent->id}/convert")->assertUnprocessable()->assertJsonValidationErrors('domain');

    $accepted = quoteIn(QuoteStatus::Accepted);
    $response = $this->post("/quotes/{$accepted->id}/convert");

    $invoice = Invoice::query()->where('converted_from_id', $accepted->id)->sole();
    $response->assertRedirect("/invoices/{$invoice->id}");

    inertiaGet("/invoices/{$invoice->id}")->assertJsonPath('props.related.converted_from.full_number', $accepted->full_number);
    inertiaGet("/quotes/{$accepted->id}")
        ->assertJsonPath('props.related.converted_to.id', $invoice->id)
        ->assertJsonPath('props.can.convert', false)
        ->assertJsonPath('props.form', null);
});

it('duplicar crea un presupuesto nuevo en borrador con el mismo contenido', function () {
    $quote = quoteIn(QuoteStatus::Sent);

    $copy = app(DuplicateDocument::class)($quote);

    expect($copy)->toBeInstanceOf(Quote::class)
        ->and($copy->id)->not->toBe($quote->id)
        ->and($copy->status)->toBe(QuoteStatus::Draft)
        ->and($copy->full_number)->toBeNull()
        ->and($copy->customer_snapshot)->toBeNull()
        ->and($copy->lines()->count())->toBe(2)
        ->and($copy->total->cents)->toBe($quote->refresh()->total->cents);
});
