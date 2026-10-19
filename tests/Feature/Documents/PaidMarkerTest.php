<?php

declare(strict_types=1);

use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use Carbon\CarbonImmutable;

const PAID_TODAY = '2026-10-19';

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock(PAID_TODAY.' 10:00:00'));
    $this->travelTo(PAID_TODAY.' 10:00:00');
    configuredIssuer();
});

function collectableInvoice(array $attributes = []): Invoice
{
    return Invoice::factory()->issued(CarbonImmutable::parse('2026-10-01'))->create(['due_date' => '2026-10-31', ...$attributes]);
}

describe('marcar como cobrada', function () {
    it('fija la fecha y la nota, la deja cobrada y lo apunta en el historial', function () {
        $invoice = collectableInvoice();

        $this->post("/invoices/{$invoice->id}/paid", ['paid_on' => '2026-10-15', 'note' => 'Transferencia'])
            ->assertRedirect("/invoices/{$invoice->id}")
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        expect($invoice->paid_at?->toDateString())->toBe('2026-10-15')
            ->and($invoice->paid_note)->toBe('Transferencia')
            ->and($invoice->paymentStatus())->toBe(PaymentStatus::Paid)
            ->and($invoice->events()->latest('created_at')->first()?->event)->toBe(DocumentEventType::MarkedPaid);
    });

    it('un borrador no se puede marcar: 422', function () {
        $draft = Invoice::factory()->draft()->withTotals()->create();

        $this->postJson("/invoices/{$draft->id}/paid", ['paid_on' => PAID_TODAY])->assertUnprocessable()->assertJsonValidationErrors('domain');
        expect($draft->refresh()->paid_at)->toBeNull();
    });

    it('no admite una fecha de cobro futura', function () {
        $invoice = collectableInvoice();

        $this->postJson("/invoices/{$invoice->id}/paid", ['paid_on' => '2026-10-20'])->assertUnprocessable()->assertJsonValidationErrors('paid_on');
        expect($invoice->refresh()->paid_at)->toBeNull();
    });

    it('una factura de total cero ya cuenta como cobrada y no se marca: NothingToCollect', function () {
        $zero = Invoice::factory()->issued(CarbonImmutable::parse('2026-10-01'))->withTotals(0, 0)->create();

        expect($zero->paymentStatus())->toBe(PaymentStatus::Paid);
        $this->postJson("/invoices/{$zero->id}/paid", ['paid_on' => PAID_TODAY])->assertUnprocessable()->assertJsonValidationErrors('domain');
    });

    it('la nota no pasa de 200 caracteres', function () {
        $invoice = collectableInvoice();

        $this->postJson("/invoices/{$invoice->id}/paid", ['paid_on' => PAID_TODAY, 'note' => str_repeat('a', 201)])->assertUnprocessable()->assertJsonValidationErrors('note');
    });

    it('solo cambia el marcador: totales, datos congelados y PDF quedan igual', function () {
        $invoice = collectableInvoice(['pdf_sha256' => str_repeat('a', 64)]);
        $before = $invoice->only(['total', 'full_number', 'issuer_snapshot', 'customer_snapshot', 'pdf_sha256']);

        $this->post("/invoices/{$invoice->id}/paid", ['paid_on' => PAID_TODAY])->assertSessionHasNoErrors();

        expect($invoice->refresh()->only(array_keys($before)))->toEqual($before);
    });
});

describe('desmarcar', function () {
    it('quita fecha y nota, vuelve a pendiente y lo apunta en el historial', function () {
        $invoice = Invoice::factory()->issued(CarbonImmutable::parse('2026-10-01'))->paid(CarbonImmutable::parse('2026-10-05'))->create(['due_date' => '2026-10-31']);

        $this->delete("/invoices/{$invoice->id}/paid")->assertRedirect("/invoices/{$invoice->id}");

        $invoice->refresh();
        expect($invoice->paid_at)->toBeNull()
            ->and($invoice->paid_note)->toBeNull()
            ->and($invoice->paymentStatus())->toBe(PaymentStatus::Unpaid)
            ->and($invoice->events()->latest('created_at')->first()?->event)->toBe(DocumentEventType::UnmarkedPaid);
    });

    it('desmarcar una vencida la devuelve a vencida', function () {
        $invoice = Invoice::factory()->issued(CarbonImmutable::parse('2026-09-01'))->paid(CarbonImmutable::parse('2026-09-10'))->create(['due_date' => '2026-09-30']);

        $this->delete("/invoices/{$invoice->id}/paid");

        expect($invoice->refresh()->paymentStatus())->toBe(PaymentStatus::Overdue);
    });
});

describe('listado y botones', function () {
    it('el filtro de vencidas trae las que pasaron su vencimiento sin marcar', function () {
        $overdue = Invoice::factory()->issued(CarbonImmutable::parse('2026-09-01'))->create(['due_date' => '2026-09-30']);
        Invoice::factory()->issued(CarbonImmutable::parse('2026-09-01'))->paid(CarbonImmutable::parse('2026-09-15'))->create(['due_date' => '2026-09-30']);
        collectableInvoice();

        $ids = inertiaGet('/invoices?payment_status=overdue')->json('props.documents.data.*.id');

        expect($ids)->toBe([$overdue->id]);
    });

    it('ofrece marcar o desmarcar según el estado, y nada en borradores o de total cero', function () {
        $pending = collectableInvoice();
        $paid = Invoice::factory()->issued(CarbonImmutable::parse('2026-10-01'))->paid()->create();
        $zero = Invoice::factory()->issued(CarbonImmutable::parse('2026-10-01'))->withTotals(0, 0)->create();
        $draft = Invoice::factory()->draft()->withTotals()->create();

        $can = fn (Invoice $invoice): array => inertiaGet("/invoices/{$invoice->id}")->json('props.can');

        expect($can($pending))->toMatchArray(['mark_paid' => true, 'unmark_paid' => false])
            ->and($can($paid))->toMatchArray(['mark_paid' => false, 'unmark_paid' => true])
            ->and($can($zero))->toMatchArray(['mark_paid' => false, 'unmark_paid' => false])
            ->and($can($draft))->toMatchArray(['mark_paid' => false, 'unmark_paid' => false]);
    });

    it('los presupuestos no tienen marcador de cobro', function () {
        $this->post('/quotes/'.fake()->uuid().'/paid', ['paid_on' => PAID_TODAY])->assertNotFound();
    });
});
