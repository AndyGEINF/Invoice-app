<?php

declare(strict_types=1);

use App\Domain\Catalog\Product;
use App\Domain\Customers\Customer;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\DocumentTax;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Address;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\UnitPrice;
use App\Infrastructure\Time\FrozenClock;

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-09-24 10:30:00'));
    Series::factory()->invoices()->create();
    configuredIssuer();
    $this->customer = Customer::factory()->create();
});

/** @param array<string, mixed> $line */
function draftLine(int $position, string $description, string $quantity, string $price, string $vat = '21', array $line = []): array
{
    return ['position' => $position, 'description' => $description, 'quantity' => $quantity, 'unit_price' => $price, 'vat_rate' => $vat, ...$line];
}

function latestInvoice(): Invoice
{
    return Invoice::query()->latest()->firstOrFail();
}

describe('alta', function () {
    it('abrir "nueva factura" no crea nada hasta guardar', function () {
        $this->get('/invoices/create')->assertOk();

        expect(Invoice::query()->count())->toBe(0);
    });

    it('POST crea un borrador sin número y lleva a su vista', function () {
        $response = $this->post('/invoices', [
            'customer_id' => $this->customer->id,
            'lines' => [draftLine(1, 'Consultoría', '2', '100')],
        ]);

        $invoice = latestInvoice();

        $response->assertRedirect("/invoices/{$invoice->id}");
        expect($invoice->status)->toBe(DocumentStatus::Draft)
            ->and($invoice->number)->toBeNull()
            ->and($invoice->full_number)->toBeNull()
            ->and($invoice->issue_date)->toBeNull()
            ->and($invoice->total->cents)->toBe(24200);
    });
});

describe('edición y recálculo', function () {
    it('PUT recalcula desglose y totales (vector V2 del contrato)', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'Inicial', '1', '1')]]);
        $invoice = latestInvoice();

        $this->put("/invoices/{$invoice->id}", [
            'customer_id' => $this->customer->id,
            'lines' => [
                draftLine(1, 'A', '3', '33.333'),
                draftLine(2, 'B', '1', '50.005'),
                draftLine(3, 'C', '1', '10.000', '10'),
            ],
        ])->assertRedirect("/invoices/{$invoice->id}")->assertSessionHasNoErrors();

        $invoice->refresh();
        $groups = $invoice->taxes()->where('tax_type', TaxType::Vat->value)->get()
            ->mapWithKeys(fn (DocumentTax $tax) => [(string) $tax->rate => [$tax->base->cents, $tax->amount->cents]])
            ->all();

        expect($invoice->taxable_base->cents)->toBe(16000)
            ->and($invoice->vat_total->cents)->toBe(3250)
            ->and($invoice->total->cents)->toBe(19250)
            ->and($groups)->toEqual(['21.00' => [15000, 3150], '10.00' => [1000, 100]])
            ->and($invoice->lines()->get()->map(fn (DocumentLine $line) => $line->line_base->cents)->all())->toBe([10000, 5001, 1000]);
    });

    it('reordena líneas conservando su identidad', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'A', '1', '10'), draftLine(2, 'B', '1', '20')]]);
        $invoice = latestInvoice();
        [$a, $b] = $invoice->lines()->get()->all();

        $this->put("/invoices/{$invoice->id}", ['customer_id' => $this->customer->id, 'lines' => [
            draftLine(1, 'B', '1', '20', line: ['id' => $b->id]),
            draftLine(2, 'A', '1', '10', line: ['id' => $a->id]),
        ]])->assertSessionHasNoErrors();

        expect($invoice->lines()->pluck('description')->all())->toBe(['B', 'A'])
            ->and($invoice->lines()->pluck('id')->all())->toBe([$b->id, $a->id]);
    });

    it('sustituye una línea por otra nueva en la misma posición', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'A', '1', '10')]]);
        $invoice = latestInvoice();

        $this->put("/invoices/{$invoice->id}", ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'C', '1', '10')]])
            ->assertSessionHasNoErrors();

        expect($invoice->lines()->pluck('description')->all())->toBe(['C'])
            ->and($invoice->refresh()->total->cents)->toBe(1210);
    });

    it('copia descripción, precio e IVA del producto si la línea no los trae', function () {
        $product = Product::factory()->priced('45.500')->vat('10.00')->create(['name' => 'Revisión anual', 'description' => null]);

        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [['position' => 1, 'product_id' => $product->id, 'quantity' => '2']]])
            ->assertSessionHasNoErrors();

        $line = latestInvoice()->lines()->sole();

        expect($line->description)->toBe('Revisión anual')
            ->and($line->unit_price->toDecimalString())->toBe('45.500')
            ->and((string) $line->vat_rate)->toBe('10.00')
            ->and($line->product_id)->toBe($product->id);
    });

    it('rechaza cantidades negativas en una factura', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'Abono', '-1', '10')]])
            ->assertSessionHasErrors('lines.0.quantity');

        expect(Invoice::query()->count())->toBe(0);
    });

    it('rechaza posiciones repetidas', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'A', '1', '10'), draftLine(1, 'B', '1', '10')]])
            ->assertSessionHasErrors('lines.1.position');
    });

    it('rechaza un cliente archivado', function () {
        $archived = Customer::factory()->archived()->create();

        $this->post('/invoices', ['customer_id' => $archived->id, 'lines' => [draftLine(1, 'A', '1', '10')]])
            ->assertSessionHasErrors('customer_id');
    });
});

describe('borrado y emitidas', function () {
    it('DELETE borra el borrador y sus líneas', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'A', '1', '10')]]);
        $invoice = latestInvoice();

        $this->delete("/invoices/{$invoice->id}")->assertRedirect('/invoices');

        expect(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse()
            ->and(DocumentLine::query()->where('document_id', $invoice->id)->exists())->toBeFalse();
    });

    it('editar un borrador lleva a su vista; editar una emitida → 422', function () {
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [draftLine(1, 'A', '1', '10')]]);
        $invoice = latestInvoice();

        $this->get("/invoices/{$invoice->id}/edit")->assertRedirect("/invoices/{$invoice->id}");

        $this->post("/invoices/{$invoice->id}/issue")->assertRedirect("/invoices/{$invoice->id}");

        $this->getJson("/invoices/{$invoice->id}/edit")->assertUnprocessable()->assertJsonValidationErrors('domain');
    });
});

describe('datos congelados al emitir', function () {
    it('cambiar después cliente, logotipo o producto no altera la factura emitida', function () {
        $product = Product::factory()->priced('80.000')->vat('21.00')->create(['name' => 'Mantenimiento', 'description' => null]);
        $this->post('/invoices', ['customer_id' => $this->customer->id, 'lines' => [['position' => 1, 'product_id' => $product->id, 'quantity' => '1']]]);
        $invoice = latestInvoice();
        $this->post("/invoices/{$invoice->id}/issue")->assertSessionHasNoErrors();

        $before = $invoice->refresh();
        $customerSnapshot = $before->customer_snapshot;
        $issuerSnapshot = $before->issuer_snapshot;

        $this->customer->update(['legal_name' => 'Otro nombre SL', 'billing_address' => Address::of('Calle Nueva 9', 'Lleida', '25001', 'Lleida')]);
        Issuer::current()->update(['logo_path' => 'logo-nuevo.png', 'company_name' => 'Empresa renombrada SL']);
        $product->update(['unit_price' => UnitPrice::fromDecimal('999'), 'name' => 'Otro producto']);

        $after = $invoice->refresh();
        $line = $after->lines()->sole();

        expect($after->customer_snapshot)->toEqual($customerSnapshot)
            ->and($after->issuer_snapshot)->toEqual($issuerSnapshot)
            ->and($after->issuer_snapshot['logo_path'])->not->toBe('logo-nuevo.png')
            ->and($line->description)->toBe('Mantenimiento')
            ->and($line->unit_price->toDecimalString())->toBe('80.000')
            ->and($after->total->cents)->toBe(9680);
    });
});
