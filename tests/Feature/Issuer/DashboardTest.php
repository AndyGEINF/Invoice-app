<?php

declare(strict_types=1);

use App\Domain\Documents\Invoice;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 10:30:00'));
});

it('la raíz lleva al panel', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

it('resume lo emitido este mes, lo pendiente y lo vencido', function () {
    configuredIssuer();
    // Emitida este mes y aún sin vencer: cuenta como emitida y como pendiente.
    Invoice::factory()->issued(CarbonImmutable::parse('2026-09-10'))->create(['due_date' => '2026-10-10']);
    // Emitida este mes y cobrada: solo cuenta como emitida.
    Invoice::factory()->issued(CarbonImmutable::parse('2026-09-05'))->paid(CarbonImmutable::parse('2026-09-06'))->create();
    // Emitida en julio y vencida en agosto: solo cuenta como vencida.
    Invoice::factory()->issued(CarbonImmutable::parse('2026-07-01'))->create(['due_date' => '2026-07-31']);
    // Un borrador no cuenta en ningún importe, pero sí sale en lo último.
    Invoice::factory()->draft()->withTotals()->create();

    inertiaGet('/dashboard')
        ->assertOk()
        ->assertJsonPath('component', 'dashboard')
        ->assertJsonPath('props.stats.issued_this_month_count', 2)
        ->assertJsonPath('props.stats.issued_this_month_total', 24200)
        ->assertJsonPath('props.stats.pending_count', 1)
        ->assertJsonPath('props.stats.pending_total', 12100)
        ->assertJsonPath('props.stats.overdue_count', 1)
        ->assertJsonPath('props.stats.overdue_total', 12100)
        ->assertJsonCount(4, 'props.recent')
        ->assertJsonPath('props.issuer_incomplete', false);
});

it('muestra como mucho los seis últimos documentos', function () {
    configuredIssuer();
    Invoice::factory()->count(8)->create();

    inertiaGet('/dashboard')->assertJsonCount(6, 'props.recent');
});

it('da la facturación de los últimos doce meses, con ceros donde no hubo facturas', function () {
    configuredIssuer();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-09-10'))->create();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-07-02'))->create();
    Invoice::factory()->issued(CarbonImmutable::parse('2025-09-30'))->create(); // fuera de la ventana
    Invoice::factory()->draft()->withTotals()->create();

    $monthly = inertiaGet('/dashboard')->assertJsonCount(12, 'props.monthly')->json('props.monthly');

    expect($monthly[0]['month'])->toBe('2025-10')
        ->and($monthly[11]['month'])->toBe('2026-09')
        ->and(array_column($monthly, 'total', 'month'))->toMatchArray(['2026-09' => 12100, '2026-07' => 12100, '2026-08' => 0, '2025-10' => 0]);
});

it('compara el mes con el anterior en porcentaje entero, y sin comparación si el anterior fue cero', function () {
    configuredIssuer();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-08-05'))->withTotals(10000, 2100)->create();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-09-05'))->withTotals(15000, 3150)->create();

    inertiaGet('/dashboard')->assertJsonPath('props.stats.change_percent', 50);
});

it('sin facturas el mes anterior no hay porcentaje', function () {
    configuredIssuer();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-09-05'))->create();

    inertiaGet('/dashboard')->assertJsonPath('props.stats.change_percent', null);
});

it('reparte lo pendiente entre en plazo y vencido', function () {
    configuredIssuer();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-09-10'))->withTotals(30000, 0)->create(['due_date' => '2026-10-10']);
    Invoice::factory()->issued(CarbonImmutable::parse('2026-07-01'))->withTotals(10000, 0)->create(['due_date' => '2026-07-31']);

    inertiaGet('/dashboard')
        ->assertJsonPath('props.stats.overdue_share_percent', 25)
        ->assertJsonPath('props.stats.outstanding_total_formatted', Money::fromCents(40000)->format());
});

it('avisa de lo que necesita atención y omite lo que no', function () {
    configuredIssuer();
    Invoice::factory()->issued(CarbonImmutable::parse('2026-07-01'))->create(['due_date' => '2026-07-31']);
    Invoice::factory()->draft()->withTotals()->create();

    $kinds = array_column(inertiaGet('/dashboard')->json('props.attention'), 'count', 'kind');

    expect($kinds)->toBe(['overdue_invoices' => 1, 'draft_invoices' => 1]);
});

it('con el emisor incompleto lo indica', function () {
    inertiaGet('/dashboard')
        ->assertJsonPath('props.issuer_incomplete', true)
        ->assertJsonPath('props.stats.issued_this_month_count', 0)
        ->assertJsonPath('props.recent', []);
});
