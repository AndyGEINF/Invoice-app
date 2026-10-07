<?php

declare(strict_types=1);

use App\Domain\Documents\Invoice;
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

it('muestra como mucho los cinco últimos documentos', function () {
    configuredIssuer();
    Invoice::factory()->count(7)->create();

    inertiaGet('/dashboard')->assertJsonCount(5, 'props.recent');
});

it('con el emisor incompleto lo indica', function () {
    inertiaGet('/dashboard')
        ->assertJsonPath('props.issuer_incomplete', true)
        ->assertJsonPath('props.stats.issued_this_month_count', 0)
        ->assertJsonPath('props.recent', []);
});
