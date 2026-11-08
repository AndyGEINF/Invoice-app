<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Application\Documents\UpdateDraft;
use App\Domain\Customers\Customer;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Documents\Exceptions\CannotRectifyInvoice;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Shared\Contracts\Clock;
use App\Http\Presenters\PrintableDocument;
use App\Infrastructure\Time\FrozenClock;

const RECTIFY_NOW = '2026-11-08 10:00:00';

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock(RECTIFY_NOW));
    $this->travelTo(RECTIFY_NOW);
    Series::factory()->invoices()->create();
    Series::factory()->creditNotes()->create();
    configuredIssuer();
});

/** Factura con dos líneas (2 × 100 € y 1 × 50 €, al 21 %), emitida salvo que se pida borrador. */
function invoiceToRectify(bool $issued = true, ?Customer $customer = null): Invoice
{
    /** @var Invoice $invoice */
    $invoice = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
        'customer_id' => ($customer ?? Customer::factory()->create())->id,
        'lines' => [
            ['position' => 1, 'description' => 'Reparación', 'quantity' => '2', 'unit_price' => '100', 'vat_rate' => '21'],
            ['position' => 2, 'description' => 'Desplazamiento', 'quantity' => '1', 'unit_price' => '50', 'vat_rate' => '21'],
        ],
    ]));

    return $issued ? app(IssueInvoice::class)($invoice)->invoice->refresh() : $invoice;
}

/** @param array<string, mixed> $overrides */
function rectifyForm(array $overrides = []): array
{
    return ['rectification_type' => 'S', 'reason' => 'Error en el NIF del cliente', ...$overrides];
}

/** La rectificativa más reciente. Los tests que crean dos avanzan el reloj entre una y otra. */
function latestCreditNote(): CreditNote
{
    /** @var CreditNote */
    return CreditNote::query()->latest('created_at')->firstOrFail();
}

describe('crear la rectificativa', function () {
    it('un borrador no se rectifica: 422 y no se crea nada', function () {
        $draft = invoiceToRectify(issued: false);

        $this->postJson("/invoices/{$draft->id}/rectify", rectifyForm())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        expect(CreditNote::query()->count())->toBe(0);
    });

    it('sobre una emitida crea una rectificativa en borrador con las líneas y el cliente', function () {
        $invoice = invoiceToRectify();

        $response = $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());

        $creditNote = latestCreditNote();
        $response->assertRedirect("/credit-notes/{$creditNote->id}");

        expect($creditNote->status)->toBe(DocumentStatus::Draft)
            ->and($creditNote->number)->toBeNull()
            ->and($creditNote->rectifies_id)->toBe($invoice->id)
            ->and($creditNote->rectification_type)->toBe(RectificationType::Substitution)
            ->and($creditNote->rectification_reason)->toBe('Error en el NIF del cliente')
            ->and($creditNote->customer_id)->toBe($invoice->customer_id)
            ->and($creditNote->lines->pluck('description')->all())->toBe(['Reparación', 'Desplazamiento'])
            ->and($creditNote->lines->first()->quantity->value)->toBe('2.0000')
            ->and($creditNote->total->cents)->toBe($invoice->total->cents);

        // La original no cambia hasta que la rectificativa se emite.
        expect($invoice->refresh()->status)->toBe(DocumentStatus::Issued);
    });

    it('el motivo es obligatorio y el tipo solo puede ser S o I', function () {
        $invoice = invoiceToRectify();

        $this->postJson("/invoices/{$invoice->id}/rectify", rectifyForm(['reason' => '  ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/invoices/{$invoice->id}/rectify", rectifyForm(['rectification_type' => 'X']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rectification_type');

        $this->postJson("/invoices/{$invoice->id}/rectify", rectifyForm(['reason' => str_repeat('a', 501)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        expect(CreditNote::query()->count())->toBe(0);
    });

    it('una rectificativa no se rectifica: la ruta solo existe para facturas', function () {
        $creditNote = CreditNote::factory()->issued()->create();

        $this->postJson("/credit-notes/{$creditNote->id}/rectify", rectifyForm())->assertNotFound();
    });

    it('por diferencias parte de las líneas en negativo, anulando la factura entera', function () {
        $invoice = invoiceToRectify();

        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm(['rectification_type' => 'I']));

        $creditNote = latestCreditNote();

        expect($creditNote->lines->map(fn ($line) => $line->quantity->value)->all())->toBe(['-2.0000', '-1.0000'])
            ->and($creditNote->lines->first()->unit_price->toDecimalString())->toBe('100.000')
            ->and($creditNote->total->cents)->toBe(-$invoice->total->cents);
    });
});

describe('importes de una rectificativa por diferencias', function () {
    it('una línea de cantidad -2 da base, cuota y total negativos redondeados por grupo', function () {
        $invoice = invoiceToRectify();
        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm(['rectification_type' => 'I', 'reason' => 'Devolución de 2 unidades']));
        $creditNote = latestCreditNote();

        app(UpdateDraft::class)($creditNote, DraftData::fromArray([
            'customer_id' => $creditNote->customer_id,
            'lines' => [
                ['position' => 1, 'description' => 'Devolución reparación', 'quantity' => '-2', 'unit_price' => '33.333', 'vat_rate' => '21'],
            ],
        ]));

        $creditNote->refresh();

        // -2 × 33,333 = -66,666 → base -66,67; cuota 21 % de -66,67 = -14,0007 → -14,00.
        expect($creditNote->taxable_base->cents)->toBe(-6667)
            ->and($creditNote->vat_total->cents)->toBe(-1400)
            ->and($creditNote->total->cents)->toBe(-8067)
            ->and($creditNote->taxes()->first()->base->cents)->toBe(-6667);
    });
});

describe('emitir la rectificativa', function () {
    it('usa la serie R, R1 por sustitución, y la original pasa a rectificada', function () {
        $invoice = invoiceToRectify();
        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());

        $creditNote = app(IssueInvoice::class)(latestCreditNote())->invoice->refresh();
        $invoice->refresh();

        expect($creditNote->full_number)->toBe('R2026-0001')
            ->and($creditNote->invoice_type)->toBe(InvoiceType::R1)
            ->and($creditNote->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->status)->toBe(DocumentStatus::Rectified)
            ->and($invoice->rectified_at?->toDateTimeString())->toBe(RECTIFY_NOW);

        $event = $invoice->events()->where('event', DocumentEventType::Rectified->value)->sole();
        expect($event->payload['full_number'])->toBe('R2026-0001');
    });

    it('por diferencias es R4', function () {
        $invoice = invoiceToRectify();
        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm(['rectification_type' => 'I']));

        $creditNote = app(IssueInvoice::class)(latestCreditNote())->invoice->refresh();

        expect($creditNote->invoice_type)->toBe(InvoiceType::R4)
            ->and($creditNote->total->cents)->toBe(-$invoice->total->cents);
    });

    it('si la original es simplificada (F2) es R5', function () {
        $invoice = invoiceToRectify(customer: Customer::factory()->individual()->withoutTaxId()->create());
        expect($invoice->invoice_type)->toBe(InvoiceType::F2);

        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());

        expect(app(IssueInvoice::class)(latestCreditNote())->invoice->refresh()->invoice_type)->toBe(InvoiceType::R5);
    });

    it('una factura enviada también pasa a rectificada', function () {
        $invoice = invoiceToRectify();
        $invoice->forceFill(['status' => DocumentStatus::Sent, 'sent_at' => now()])->save();
        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());

        app(IssueInvoice::class)(latestCreditNote());

        expect($invoice->refresh()->status)->toBe(DocumentStatus::Rectified);
    });

    it('admite una segunda rectificativa: la original conserva la primera fecha y las lista las dos', function () {
        $invoice = invoiceToRectify();

        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());
        app(IssueInvoice::class)(latestCreditNote());
        $firstRectifiedAt = $invoice->refresh()->rectified_at;

        $this->travel(1)->days();
        app()->instance(Clock::class, new FrozenClock(now()->toDateTimeString()));

        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm(['rectification_type' => 'I', 'reason' => 'Devolución']))
            ->assertRedirect();
        app(IssueInvoice::class)(latestCreditNote());

        $invoice->refresh();

        expect($invoice->status)->toBe(DocumentStatus::Rectified)
            ->and($invoice->rectified_at->equalTo($firstRectifiedAt))->toBeTrue()
            ->and($invoice->rectifications()->orderBy('number')->pluck('full_number')->all())->toBe(['R2026-0001', 'R2026-0002'])
            ->and($invoice->events()->where('event', DocumentEventType::Rectified->value)->count())->toBe(2);

        $this->get("/invoices/{$invoice->id}")->assertInertia(fn ($page) => $page
            ->has('related.rectified_by', 2)
            ->where('can.rectify', true));
    });

    it('no se emite si la factura que corrige no está emitida', function () {
        $draft = invoiceToRectify(issued: false);
        $creditNote = CreditNote::factory()->create(['rectifies_id' => $draft->id]);
        $creditNote->lines()->create([
            'position' => 1,
            'description' => 'Línea',
            'quantity' => '1',
            'unit_price' => '10',
            'vat_rate' => '21',
        ]);

        expect(fn () => app(IssueInvoice::class)($creditNote))->toThrow(CannotRectifyInvoice::class);
        expect($creditNote->refresh()->number)->toBeNull();
    });
});

describe('lo que se ve', function () {
    it('el PDF de la rectificativa cita a la original con su fecha y el motivo', function () {
        $invoice = invoiceToRectify();
        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());
        $creditNote = app(IssueInvoice::class)(latestCreditNote())->invoice->refresh();

        $paper = PrintableDocument::from($creditNote, embedLogo: false);

        expect($paper->rectifies)->toContain('F2026-0001')
            ->and($paper->rectifies)->toContain('08/11/2026')
            ->and($paper->rectifies)->toContain('Error en el NIF del cliente');
    });

    it('la factura emitida ofrece rectificar y el borrador no', function () {
        $this->get('/invoices/'.invoiceToRectify()->id)->assertInertia(fn ($page) => $page->where('can.rectify', true));
        $this->get('/invoices/'.invoiceToRectify(issued: false)->id)->assertInertia(fn ($page) => $page->where('can.rectify', false));
    });

    it('la rectificativa muestra a qué factura corrige, el tipo y el motivo', function () {
        $invoice = invoiceToRectify();
        $this->post("/invoices/{$invoice->id}/rectify", rectifyForm());
        $creditNote = latestCreditNote();

        $this->get("/credit-notes/{$creditNote->id}")->assertInertia(fn ($page) => $page
            ->where('related.rectifies.id', $invoice->id)
            ->where('document.rectification_type_label', 'Por sustitución')
            ->where('document.rectification_reason', 'Error en el NIF del cliente')
            ->where('can.rectify', false)
            ->where('can.duplicate', false));
    });
});
