<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Application\Documents\IssueResult;
use App\Domain\Customers\Customer;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Exceptions\DocumentHasNoLines;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Exceptions\IssueDateBeforeSeriesLast;
use App\Domain\Documents\Exceptions\IssueDateInFuture;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Exceptions\IssuerNotConfigured;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Tax\Exceptions\MissingExemptionCode;
use App\Events\InvoiceIssued;
use App\Infrastructure\Time\FrozenClock;
use Carbon\CarbonImmutable;

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-09-24 10:30:00'));

    $this->series = Series::factory()->invoices()->create();
    configuredIssuer();
});

/** @param list<array<string, mixed>>|null $lines */
function invoiceDraft(?Customer $customer = null, ?array $lines = null): Invoice
{
    $lines ??= [['position' => 1, 'description' => 'Consultoría', 'quantity' => '2', 'unit_price' => '100', 'vat_rate' => '21']];

    /** @var Invoice */
    return app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
        'customer_id' => $customer?->id,
        'lines' => $lines,
    ]));
}

function issue(Invoice $invoice, ?string $issueDate = null): IssueResult
{
    return app(IssueInvoice::class)($invoice, issueDate: $issueDate !== null ? CarbonImmutable::parse($issueDate) : null);
}

describe('emisor incompleto', function () {
    it('no emite sin logotipo e indica lo que falta', function () {
        Issuer::current()->update(['logo_path' => null]);
        $draft = invoiceDraft(Customer::factory()->create());

        expect(fn () => issue($draft))->toThrow(function (IssuerNotConfigured $e) {
            expect($e->missing)->toBe([Issuer::MISSING_LOGO]);
        });

        expect($draft->refresh()->status)->toBe(DocumentStatus::Draft)
            ->and($draft->number)->toBeNull()
            ->and($this->series->refresh()->next_number)->toBe(1);
    });

    it('no emite sin NIF', function () {
        Issuer::current()->update(['tax_id' => null, 'tax_id_type' => null]);

        expect(fn () => issue(invoiceDraft(Customer::factory()->create())))
            ->toThrow(function (IssuerNotConfigured $e) {
                expect($e->missing)->toContain(Issuer::MISSING_TAX_ID);
            });
    });
});

describe('emisión', function () {
    it('asigna número, fechas, clasificación, snapshots y estado', function () {
        $customer = Customer::factory()->create(['payment_terms_days' => 45]);

        $result = issue(invoiceDraft($customer));
        $invoice = $result->invoice->refresh();

        expect($invoice->full_number)->toBe('F2026-0001')
            ->and($invoice->number)->toBe(1)
            ->and($invoice->fiscal_year)->toBe(2026)
            ->and($invoice->series_id)->toBe($this->series->id)
            ->and($invoice->issue_date->toDateString())->toBe('2026-09-24')
            ->and($invoice->due_date->toDateString())->toBe('2026-11-08')
            ->and($invoice->invoice_type)->toBe(InvoiceType::F1)
            ->and($invoice->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->issued_at->toDateTimeString())->toBe('2026-09-24 10:30:00')
            ->and($invoice->total->cents)->toBe(24200)
            ->and($result->warnings)->toBe([]);

        $issuer = Issuer::current();
        expect($invoice->issuer_snapshot)
            ->toMatchArray([
                'snapshot_version' => Issuer::SNAPSHOT_VERSION,
                'legal_name' => $issuer->legalName(),
                'contact_name' => $issuer->name,
                'logo_path' => $issuer->logo_path,
                'tax_id' => $issuer->tax_id,
            ])
            ->and($invoice->customer_snapshot)
            ->toMatchArray(['legal_name' => $customer->legal_name, 'tax_id' => $customer->tax_id]);

        expect($invoice->events()->pluck('event')->all())
            ->toBe([DocumentEventType::Created, DocumentEventType::Issued]);
    });

    it('guarda en el historial el número, el tipo y el total', function () {
        $invoice = issue(invoiceDraft(Customer::factory()->create()))->invoice;

        $event = $invoice->events()->where('event', DocumentEventType::Issued->value)->sole();

        // jsonb no conserva el orden de las claves.
        expect($event->payload)->toEqual([
            'full_number' => 'F2026-0001',
            'invoice_type' => InvoiceType::F1->value,
            'total_cents' => 24200,
        ]);
    });

    it('lanza el evento InvoiceIssued', function () {
        Event::fake([InvoiceIssued::class]);

        $invoice = issue(invoiceDraft(Customer::factory()->create()))->invoice;

        Event::assertDispatched(InvoiceIssued::class, fn (InvoiceIssued $e) => $e->documentId === $invoice->id);
    });

    it('numera de forma correlativa', function () {
        $customer = Customer::factory()->create();

        expect(issue(invoiceDraft($customer))->invoice->full_number)->toBe('F2026-0001')
            ->and(issue(invoiceDraft($customer))->invoice->full_number)->toBe('F2026-0002');
    });

    it('usa el plazo de pago por defecto si no hay cliente', function () {
        $invoice = issue(invoiceDraft())->invoice->refresh();

        expect($invoice->due_date->toDateString())->toBe('2026-10-24');
    });

    it('respeta la fecha de vencimiento del borrador', function () {
        $draft = invoiceDraft(Customer::factory()->create());
        $draft->update(['due_date' => '2026-10-01']);

        expect(issue($draft)->invoice->refresh()->due_date->toDateString())->toBe('2026-10-01');
    });

    it('no se puede emitir dos veces', function () {
        $invoice = issue(invoiceDraft(Customer::factory()->create()))->invoice;

        issue($invoice);
    })->throws(DocumentIsImmutable::class);
});

describe('validaciones', function () {
    it('no emite una factura sin líneas ni consume número', function () {
        $draft = invoiceDraft(Customer::factory()->create(), []);

        expect(fn () => issue($draft))->toThrow(DocumentHasNoLines::class)
            ->and($this->series->refresh()->next_number)->toBe(1);
    });

    it('no emite una línea exenta sin causa de exención', function () {
        $draft = invoiceDraft(Customer::factory()->create());
        // El formulario ya lo impide; aquí se fuerza para probar la última barrera.
        DocumentLine::query()->where('document_id', $draft->id)->update(['vat_rate' => '0.00', 'exemption_code' => null]);

        expect(fn () => issue($draft))->toThrow(MissingExemptionCode::class)
            ->and($draft->refresh()->status)->toBe(DocumentStatus::Draft)
            ->and($this->series->refresh()->next_number)->toBe(1);
    });

    it('no admite una fecha de expedición futura', function () {
        issue(invoiceDraft(Customer::factory()->create()), '2026-09-25');
    })->throws(IssueDateInFuture::class);

    it('no admite una fecha anterior a la última factura de la serie', function () {
        $customer = Customer::factory()->create();
        issue(invoiceDraft($customer), '2026-09-20');

        issue(invoiceDraft($customer), '2026-09-19');
    })->throws(IssueDateBeforeSeriesLast::class);

    it('admite una fecha pasada que respeta el orden de la serie', function () {
        $customer = Customer::factory()->create();
        issue(invoiceDraft($customer), '2026-09-10');

        $invoice = issue(invoiceDraft($customer), '2026-09-10')->invoice;

        expect($invoice->issue_date->toDateString())->toBe('2026-09-10')
            ->and($invoice->full_number)->toBe('F2026-0002');
    });
});

describe('factura simplificada', function () {
    it('clasifica como F2 si el cliente no tiene NIF', function () {
        $invoice = issue(invoiceDraft(Customer::factory()->withoutTaxId()->create()))->invoice;

        expect($invoice->invoice_type)->toBe(InvoiceType::F2);
    });

    it('avisa si supera el límite de la simplificada, pero la emite', function () {
        $lines = [['position' => 1, 'description' => 'Equipo', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate' => '21']];

        $result = issue(invoiceDraft(null, $lines));

        expect($result->invoice->invoice_type)->toBe(InvoiceType::F2)
            ->and($result->invoice->status)->toBe(DocumentStatus::Issued)
            ->and($result->warnings)->toBe([IssueResult::WARNING_SIMPLIFIED_OVER_LIMIT]);
    });

    it('no avisa por debajo del límite', function () {
        expect(issue(invoiceDraft())->warnings)->toBe([]);
    });
});
