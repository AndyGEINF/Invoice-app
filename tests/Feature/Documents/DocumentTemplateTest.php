<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Domain\Customers\Customer;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use Illuminate\Support\Facades\Storage;

/*
| La plantilla del PDF (resources/views/pdf/document.blade.php) se comprueba a
| través de la vista previa, que es la misma plantilla con el logotipo incrustado.
*/

/** Separador entre la cifra y el símbolo del euro en es-ES. */
const EURO = "\u{00A0}€";

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-10-20 10:00:00'));
    Series::factory()->invoices()->create();
    configuredIssuer();
    Storage::fake(config('invoice.storage.logos_disk'))
        ->put((string) Issuer::current()->logo_path, (string) file_get_contents(__DIR__.'/../../Fixtures/logo.png'));
});

/**
 * Factura emitida por el flujo real, para que lleve snapshots y desglose.
 *
 * @param  list<array<string, mixed>>  $lines
 */
function templateInvoice(?Customer $customer, array $lines, string $irpfRate = '0'): Invoice
{
    /** @var Invoice $invoice */
    $invoice = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
        'customer_id' => $customer?->id,
        'irpf_rate' => $irpfRate,
        'lines' => $lines,
    ]));
    app(IssueInvoice::class)($invoice);

    return $invoice->refresh();
}

/** @return array<string, mixed> */
function templateLine(array $overrides = []): array
{
    return ['position' => 1, 'description' => 'Desarrollo web', 'quantity' => '2', 'unit_price' => '10000', 'vat_rate' => '21', ...$overrides];
}

it('factura completa: logotipo, emisor del snapshot, cliente e importes en formato español', function () {
    $invoice = templateInvoice(Customer::factory()->create(['legal_name' => 'Talleres Pérez SL']), [templateLine()]);
    Issuer::current()->update(['company_name' => 'Nombre Nuevo SL']);

    $this->get("/invoices/{$invoice->id}/preview")
        ->assertOk()
        ->assertSee('src="data:image/png;base64,', escape: false)
        ->assertSee('Factura')
        ->assertSee($invoice->full_number)
        ->assertSee('Demo SL')
        ->assertSee('Andy Moreno')
        ->assertDontSee('Nombre Nuevo SL')
        ->assertSee('Talleres Pérez SL')
        ->assertSee('20.000,00'.EURO)
        ->assertSee('4.200,00'.EURO)
        ->assertSee('24.200,00'.EURO);
});

it('sin NIF del cliente es una factura simplificada', function () {
    $invoice = templateInvoice(Customer::factory()->withoutTaxId()->create(), [templateLine()]);

    $this->get("/invoices/{$invoice->id}/preview")->assertSee('Factura simplificada');
});

it('con retención muestra el IRPF restando', function () {
    $invoice = templateInvoice(Customer::factory()->withIrpf()->create(), [templateLine(['irpf_applies' => true])], '15');

    $this->get("/invoices/{$invoice->id}/preview")
        ->assertSee('Retención IRPF')
        ->assertSee('-3.000,00'.EURO)
        ->assertSee('21.200,00'.EURO);
});

it('una línea exenta imprime el texto legal de su causa', function () {
    $invoice = templateInvoice(Customer::factory()->create(), [templateLine(['vat_rate' => '0', 'exemption_code' => 'E1'])]);

    $this->get("/invoices/{$invoice->id}/preview")->assertSee('E1: '.ExemptionCode::E1->legalText());
});

it('la rectificativa cita la factura original, su fecha y el motivo', function () {
    $original = templateInvoice(Customer::factory()->create(), [templateLine()]);
    $creditNote = CreditNote::factory()->issued()->create([
        'rectifies_id' => $original->id,
        'rectification_reason' => 'Precio mal aplicado',
    ]);

    $this->get("/credit-notes/{$creditNote->id}/preview")
        ->assertSee('Factura rectificativa')
        ->assertSee("Rectifica a la factura {$original->full_number} de fecha 20/10/2026 — Precio mal aplicado");
});
