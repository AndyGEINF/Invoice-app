<?php

declare(strict_types=1);

use App\Domain\Catalog\Product;
use App\Domain\Customers\Contact;
use App\Domain\Customers\Customer;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;

describe('factories', function () {
    it('rellenan un emisor completo', function () {
        $issuer = configuredIssuer();

        expect($issuer->isComplete())->toBeTrue()
            ->and($issuer->legalName())->toBe('Demo SL')
            ->and(Issuer::query()->count())->toBe(1);
    });

    it('generan clientes con identificador fiscal válido', function () {
        $customers = Customer::factory()->count(20)->create();
        $individuals = Customer::factory()->individual()->count(20)->create();

        expect($customers->every(fn (Customer $c) => $c->taxId()?->isValid()))->toBeTrue()
            ->and($individuals->every(fn (Customer $c) => $c->taxId()?->isValid()))->toBeTrue();
    });

    it('generan clientes sin NIF e intracomunitarios', function () {
        expect(Customer::factory()->withoutTaxId()->create()->hasTaxId())->toBeFalse()
            ->and(Customer::factory()->intraCommunity()->create()->taxId()?->isEuVat())->toBeTrue();
    });

    it('generan contactos, productos y series', function () {
        expect(Contact::factory()->default()->create()->is_default)->toBeTrue()
            ->and(Product::factory()->service()->priced('33.333')->create()->unit_price->thousandths)->toBe(33333)
            ->and(Series::factory()->invoices()->create()->code)->toBe('F');
    });

    it('generan documentos de cada tipo con su clase', function () {
        expect(Invoice::factory()->create())->toBeInstanceOf(Invoice::class)
            ->and(Quote::factory()->create())->toBeInstanceOf(Quote::class)
            ->and(CreditNote::factory()->create())->toBeInstanceOf(CreditNote::class);
    });

    it('generan facturas emitidas que cumplen las restricciones de la base de datos', function () {
        $invoice = Invoice::factory()->issued()->create()->refresh();

        expect($invoice->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->full_number)->toMatch('/^F\d{4}-\d{4}$/')
            ->and($invoice->issuer_snapshot['legal_name'])->toBe('Demo SL')
            ->and($invoice->customer_snapshot['legal_name'])->toBe($invoice->customer->legal_name)
            ->and($invoice->due_date->equalTo($invoice->issue_date->addDays(30)))->toBeTrue()
            ->and($invoice->total->cents)->toBe(12100);
    });

    it('generan facturas cobradas y vencidas', function () {
        expect(Invoice::factory()->issued()->paid()->create()->paymentStatus())->toBe(PaymentStatus::Paid)
            ->and(Invoice::factory()->overdue()->create()->paymentStatus())->toBe(PaymentStatus::Overdue);
    });

    it('generan presupuestos enviados y caducados', function () {
        expect(Quote::factory()->sent()->create()->status)->toBe(QuoteStatus::Sent)
            ->and(Quote::factory()->expired()->create()->isExpired())->toBeTrue();
    });

    it('generan rectificativas enlazadas a una factura emitida', function () {
        $credit = CreditNote::factory()->create();

        expect($credit->rectifies)->toBeInstanceOf(Invoice::class)
            ->and($credit->rectifies->status)->toBe(DocumentStatus::Issued);
    });

    it('generan líneas de documento', function () {
        $line = DocumentLine::factory()->of('3', '33.333')->create();

        expect($line->quantity->value)->toBe('3.0000')
            ->and($line->unit_price->thousandths)->toBe(33333);
    });
});
