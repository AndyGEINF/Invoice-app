<?php

declare(strict_types=1);

use App\Domain\Customers\Customer;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\InvoiceTypeResolver;
use App\Domain\Documents\Quote;
use App\Domain\Documents\SnapshotFactory;
use App\Domain\Shared\Address;

describe('InvoiceTypeResolver', function () {
    it('factura completa si el cliente tiene NIF', function () {
        $invoice = Invoice::factory()->create(['customer_id' => Customer::factory()]);

        expect((new InvoiceTypeResolver)->resolve($invoice))->toBe(InvoiceType::F1);
    });

    it('factura simplificada si el cliente no tiene NIF', function () {
        $invoice = Invoice::factory()->create(['customer_id' => Customer::factory()->withoutTaxId()]);

        expect((new InvoiceTypeResolver)->resolve($invoice))->toBe(InvoiceType::F2);
    });

    it('factura simplificada si no hay cliente', function () {
        $invoice = Invoice::factory()->create(['customer_id' => null]);

        expect((new InvoiceTypeResolver)->resolve($invoice))->toBe(InvoiceType::F2);
    });

    it('usa el NIF congelado cuando ya existe el snapshot', function () {
        $invoice = Invoice::factory()->create(['customer_snapshot' => ['tax_id' => null]]);

        expect((new InvoiceTypeResolver)->resolve($invoice))->toBe(InvoiceType::F2);
    });

    it('rectificativa por sustitución es R1 y por diferencias R4', function () {
        $original = Invoice::factory()->issued()->create();

        $substitution = CreditNote::factory()->create([
            'rectifies_id' => $original->id,
            'rectification_type' => RectificationType::Substitution,
        ]);
        $differences = CreditNote::factory()->create([
            'rectifies_id' => $original->id,
            'rectification_type' => RectificationType::Differences,
        ]);

        expect((new InvoiceTypeResolver)->resolve($substitution))->toBe(InvoiceType::R1)
            ->and((new InvoiceTypeResolver)->resolve($differences))->toBe(InvoiceType::R4);
    });

    it('rectificativa de una simplificada es R5', function () {
        $simplified = Invoice::factory()->issued()->create(['invoice_type' => InvoiceType::F2]);
        $credit = CreditNote::factory()->create([
            'rectifies_id' => $simplified->id,
            'rectification_type' => RectificationType::Differences,
        ]);

        expect((new InvoiceTypeResolver)->resolve($credit))->toBe(InvoiceType::R5);
    });

    it('un presupuesto no tiene clasificación fiscal', function () {
        (new InvoiceTypeResolver)->resolve(Quote::factory()->create());
    })->throws(LogicException::class);
});

describe('SnapshotFactory', function () {
    it('congela emisor y cliente aunque cambien después', function () {
        $issuer = configuredIssuer();
        $customer = Customer::factory()->create(['legal_name' => 'Talleres Pérez SL']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        (new SnapshotFactory)->freezeInto($invoice, $issuer, $customer);
        $invoice->save();

        $customer->update([
            'legal_name' => 'Otro nombre SL',
            'billing_address' => Address::of('Otra calle 2', 'Madrid', '28001'),
        ]);
        $issuer->update(['logo_path' => 'nuevo.png']);

        $invoice->refresh();

        expect($invoice->customer_snapshot['legal_name'])->toBe('Talleres Pérez SL')
            ->and($invoice->issuer_snapshot['logo_path'])->toBe('test-logo.png')
            ->and($invoice->issuer_snapshot['legal_name'])->toBe('Demo SL');
    });

    it('deja el cliente vacío en una simplificada sin cliente', function () {
        $invoice = Invoice::factory()->create(['customer_id' => null]);

        (new SnapshotFactory)->freezeInto($invoice, configuredIssuer(), null);

        expect($invoice->customer_snapshot)->toBeNull()
            ->and($invoice->issuer_snapshot)->toBeArray();
    });
});
