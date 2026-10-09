<?php

declare(strict_types=1);

use App\Domain\Customers\Customer;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
});

/** Factura emitida de 121,00 € (100 + 21 % IVA). */
function customerInvoice(Customer $customer, string $issueDate, string $dueDate, ?string $paidAt = null): Invoice
{
    return Invoice::factory()->issued(CarbonImmutable::parse($issueDate))->for($customer)->create([
        'due_date' => $dueDate,
        'paid_at' => $paidAt,
    ]);
}

describe('listado', function () {
    it('cada fila dice lo que debe el cliente y qué parte está vencida', function () {
        $customer = Customer::factory()->create(['legal_name' => 'Ferretería SL']);
        customerInvoice($customer, '2026-09-01', '2026-09-30');              // vencida
        customerInvoice($customer, '2026-10-01', '2026-10-31');              // en plazo
        customerInvoice($customer, '2026-09-01', '2026-09-30', '2026-09-15'); // cobrada

        inertiaGet('/customers')
            ->assertJsonPath('props.customers.data.0.unpaid_total', 24200)
            ->assertJsonPath('props.customers.data.0.overdue_total', 12100)
            ->assertJsonPath('props.stats.pending_count', 1)
            ->assertJsonPath('props.stats.overdue_count', 1);
    });

    it('ordena el top por lo facturado este año, sin contar otros años', function () {
        $big = Customer::factory()->create(['legal_name' => 'Grande SL']);
        $small = Customer::factory()->create(['legal_name' => 'Pequeña SL']);
        customerInvoice($big, '2026-03-01', '2026-03-31');
        customerInvoice($big, '2026-04-01', '2026-04-30');
        customerInvoice($small, '2026-05-01', '2026-05-31');
        customerInvoice($small, '2025-12-01', '2025-12-31'); // año pasado: no cuenta

        inertiaGet('/customers')
            ->assertJsonPath('props.top_customers.0.display_name', 'Grande SL')
            ->assertJsonPath('props.top_customers.0.billed_total', 24200)
            ->assertJsonPath('props.top_customers.0.share_percent', 100)
            ->assertJsonPath('props.top_customers.1.billed_total', 12100)
            ->assertJsonPath('props.top_customers.1.share_percent', 50);
    });

    it('los documentos pendientes no incluyen cobradas ni borradores, y van por vencimiento', function () {
        $customer = Customer::factory()->create();
        $later = customerInvoice($customer, '2026-10-01', '2026-10-31');
        $sooner = customerInvoice($customer, '2026-09-01', '2026-09-30');
        customerInvoice($customer, '2026-09-01', '2026-09-30', '2026-09-20');
        Invoice::factory()->draft()->withTotals()->for($customer)->create();

        inertiaGet('/customers')
            ->assertJsonCount(2, 'props.pending_documents')
            ->assertJsonPath('props.pending_documents.0.id', $sooner->id)
            ->assertJsonPath('props.pending_documents.1.id', $later->id);
    });

    it('?selected= trae el resumen del cliente con su actividad', function () {
        $customer = Customer::factory()->create(['notes' => 'Llamar por las mañanas']);
        customerInvoice($customer, '2026-09-01', '2026-09-30');
        Quote::factory()->for($customer)->create();

        inertiaGet('/customers')->assertJsonPath('props.selected', null);

        inertiaGet("/customers?selected={$customer->id}")
            ->assertJsonPath('props.selected.id', $customer->id)
            ->assertJsonPath('props.selected.notes', 'Llamar por las mañanas')
            ->assertJsonPath('props.selected.overdue_total', 12100)
            ->assertJsonPath('props.selected.billed_year_total_formatted', Money::fromCents(12100)->format())
            ->assertJsonCount(2, 'props.selected.recent_documents');
    });

    it('un ?selected= que no es un cliente se ignora', function () {
        inertiaGet('/customers?selected=no-es-un-uuid')->assertOk()->assertJsonPath('props.selected', null);
    });
});

describe('ficha', function () {
    it('separa facturas de presupuestos y da las cifras del año', function () {
        $customer = Customer::factory()->create();
        customerInvoice($customer, '2026-09-01', '2026-09-30');
        customerInvoice($customer, '2025-06-01', '2025-06-30', '2025-06-10');
        Quote::factory()->count(2)->for($customer)->create();

        inertiaGet("/customers/{$customer->id}")
            ->assertJsonCount(2, 'props.invoices')
            ->assertJsonCount(2, 'props.quotes')
            ->assertJsonPath('props.stats.year', 2026)
            ->assertJsonPath('props.stats.billed_year_count', 1)
            ->assertJsonPath('props.stats.unpaid_total', 12100)
            ->assertJsonPath('props.stats.overdue_total', 12100);
    });

    it('guarda las notas sin tocar el resto del cliente', function () {
        $customer = Customer::factory()->create(['legal_name' => 'Sin cambios SL', 'notes' => null]);

        $this->from("/customers/{$customer->id}")
            ->patch("/customers/{$customer->id}/notes", ['notes' => "Paga a 60 días.\nContacto: Marta."])
            ->assertRedirect("/customers/{$customer->id}")
            ->assertInertiaFlash('success');

        $customer->refresh();
        expect($customer->notes)->toBe("Paga a 60 días.\nContacto: Marta.")
            ->and($customer->legal_name)->toBe('Sin cambios SL');
    });

    it('unas notas vacías se guardan como nulas', function () {
        $customer = Customer::factory()->create(['notes' => 'Antes']);

        $this->patch("/customers/{$customer->id}/notes", ['notes' => '   ']);

        expect($customer->refresh()->notes)->toBeNull();
    });

    it('limita la longitud de las notas', function () {
        $customer = Customer::factory()->create();

        $this->patch("/customers/{$customer->id}/notes", ['notes' => str_repeat('a', 5001)])->assertSessionHasErrors('notes');
    });
});
