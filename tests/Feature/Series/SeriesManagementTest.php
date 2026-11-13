<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Domain\Customers\Customer;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Exceptions\SeriesInactive;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use Database\Seeders\InstallSeeder;

const SERIES_NOW = '2026-11-13 10:00:00';

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock(SERIES_NOW));
    $this->travelTo(SERIES_NOW);
    $this->seed(InstallSeeder::class);
    configuredIssuer();
});

/** @param array<string, mixed> $overrides */
function seriesForm(array $overrides = []): array
{
    return [
        'document_type' => 'invoice',
        'code' => 'TALLER',
        'prefix' => 'TALLER',
        'padding' => 4,
        'next_number' => 1,
        'resets_yearly' => true,
        'is_default' => false,
        'is_active' => true,
        ...$overrides,
    ];
}

function seriesByCode(string $code): Series
{
    return Series::query()->where('code', $code)->firstOrFail();
}

/** Crea un borrador de factura y lo emite en la serie indicada. */
function issueInSeries(Series $series): Invoice
{
    /** @var Invoice $draft */
    $draft = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
        'customer_id' => Customer::factory()->create()->id,
        'lines' => [['position' => 1, 'description' => 'Reparación', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '21']],
    ]));

    return app(IssueInvoice::class)($draft, $series->id)->invoice->refresh();
}

describe('instalación', function () {
    it('InstallSeeder crea F, R y P como series por defecto', function () {
        expect(Series::defaultFor(DocumentType::Invoice)?->code)->toBe('F')
            ->and(Series::defaultFor(DocumentType::CreditNote)?->code)->toBe('R')
            ->and(Series::defaultFor(DocumentType::Quote)?->code)->toBe('P');
    });

    it('la página lista las series con sus documentos y el próximo número', function () {
        issueInSeries(seriesByCode('F'));

        $this->get('/settings/series')->assertInertia(fn ($page) => $page
            ->component('settings/series')
            ->has('series', 3)
            ->where('series.0.code', 'F')
            ->where('series.0.documents_count', 1)
            ->where('series.0.has_issued', true)
            ->where('series.0.next_full_number', 'F2026-0002'));
    });
});

describe('crear y emitir', function () {
    it('crea una serie y emite en ella empezando por el número elegido', function () {
        $this->post('/settings/series', seriesForm(['next_number' => 100]))->assertRedirect('/settings/series');

        $invoice = issueInSeries(seriesByCode('TALLER'));

        expect($invoice->full_number)->toBe('TALLER2026-0100')
            ->and(seriesByCode('TALLER')->next_number)->toBe(101);
    });

    it('al cambiar de año la serie vuelve al 1 con el año nuevo', function () {
        $this->post('/settings/series', seriesForm(['next_number' => 100]));
        issueInSeries(seriesByCode('TALLER'));

        app()->instance(Clock::class, new FrozenClock('2027-01-15 09:00:00'));
        $this->travelTo('2027-01-15 09:00:00');

        expect(issueInSeries(seriesByCode('TALLER'))->full_number)->toBe('TALLER2027-0001');
    });

    it('sin reinicio anual el número no lleva año', function () {
        $this->post('/settings/series', seriesForm(['code' => 'OBRA', 'prefix' => 'OB', 'resets_yearly' => false, 'padding' => 3]));

        expect(issueInSeries(seriesByCode('OBRA'))->full_number)->toBe('OB-001');
    });

    it('guarda código y prefijo en mayúsculas', function () {
        $this->post('/settings/series', seriesForm(['code' => 'taller', 'prefix' => 'tl']));

        expect(seriesByCode('TALLER')->prefix)->toBe('TL');
    });

    it('el código es único y valida los rangos', function () {
        $this->postJson('/settings/series', seriesForm(['code' => 'F']))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/settings/series', seriesForm(['code' => 'CON ESPACIO']))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/settings/series', seriesForm(['code' => 'DEMASIADOLARGO']))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/settings/series', seriesForm(['padding' => 9]))->assertUnprocessable()->assertJsonValidationErrors('padding');
        $this->postJson('/settings/series', seriesForm(['padding' => 0]))->assertUnprocessable()->assertJsonValidationErrors('padding');
        $this->postJson('/settings/series', seriesForm(['next_number' => 0]))->assertUnprocessable()->assertJsonValidationErrors('next_number');

        expect(Series::query()->count())->toBe(3);
    });
});

describe('series con documentos emitidos', function () {
    beforeEach(function () {
        $this->post('/settings/series', seriesForm(['next_number' => 100]));
        $this->taller = seriesByCode('TALLER');
        issueInSeries($this->taller);
    });

    it('no deja cambiar el prefijo ni el código', function () {
        $this->putJson("/settings/series/{$this->taller->id}", seriesForm(['prefix' => 'TL', 'next_number' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->putJson("/settings/series/{$this->taller->id}", seriesForm(['code' => 'OTRA', 'next_number' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        expect($this->taller->refresh()->prefix)->toBe('TALLER')
            ->and($this->taller->code)->toBe('TALLER');
    });

    it('no deja cambiar los dígitos ni el reinicio anual', function () {
        $this->putJson("/settings/series/{$this->taller->id}", seriesForm(['padding' => 5, 'next_number' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->putJson("/settings/series/{$this->taller->id}", seriesForm(['resets_yearly' => false, 'next_number' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    });

    it('no deja bajar el contador, pero sí subirlo', function () {
        $this->putJson("/settings/series/{$this->taller->id}", seriesForm(['next_number' => 50]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        expect($this->taller->refresh()->next_number)->toBe(101);

        $this->put("/settings/series/{$this->taller->id}", seriesForm(['next_number' => 200]))->assertSessionHasNoErrors();

        expect(issueInSeries($this->taller->refresh())->full_number)->toBe('TALLER2026-0200');
    });

    it('no se borra: 422; se puede desactivar', function () {
        $this->deleteJson("/settings/series/{$this->taller->id}")->assertUnprocessable()->assertJsonValidationErrors('domain');

        $this->post("/settings/series/{$this->taller->id}/toggle")->assertRedirect('/settings/series');

        expect($this->taller->refresh()->is_active)->toBeFalse();
    });

    it('una serie desactivada no se puede usar al emitir', function () {
        $this->post("/settings/series/{$this->taller->id}/toggle");

        expect(fn () => issueInSeries($this->taller->refresh()))->toThrow(SeriesInactive::class);
    });

    it('ni aparece entre las series para elegir al emitir', function () {
        $this->post("/settings/series/{$this->taller->id}/toggle");

        /** @var Invoice $draft */
        $draft = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
            'lines' => [['position' => 1, 'description' => 'Línea', 'quantity' => '1', 'unit_price' => '10', 'vat_rate' => '21']],
        ]));

        $this->get("/invoices/{$draft->id}")->assertInertia(fn ($page) => $page
            ->has('series', 1)
            ->where('series.0.code', 'F')
            ->where('series.0.next_full_number', 'F2026-0001'));
    });
});

describe('borrar', function () {
    it('una serie sin documentos se borra', function () {
        $this->post('/settings/series', seriesForm());
        $taller = seriesByCode('TALLER');

        $this->delete("/settings/series/{$taller->id}")->assertRedirect('/settings/series');

        expect(Series::query()->whereKey($taller->id)->exists())->toBeFalse();
    });

    it('con solo un borrador tampoco se borra', function () {
        $this->post('/settings/series', seriesForm());
        $taller = seriesByCode('TALLER');

        app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
            'series_id' => $taller->id,
            'lines' => [['position' => 1, 'description' => 'Línea', 'quantity' => '1', 'unit_price' => '10', 'vat_rate' => '21']],
        ]));

        $this->deleteJson("/settings/series/{$taller->id}")->assertUnprocessable()->assertJsonValidationErrors('domain');
    });
});

describe('serie por defecto', function () {
    it('es única por tipo: marcar otra se la quita a la anterior', function () {
        $this->post('/settings/series', seriesForm(['is_default' => true]));

        expect(Series::defaultFor(DocumentType::Invoice)?->code)->toBe('TALLER')
            ->and(seriesByCode('F')->is_default)->toBeFalse()
            ->and(Series::defaultFor(DocumentType::Quote)?->code)->toBe('P');
    });

    it('no se puede quitar sin marcar otra, ni desactivar, ni borrar', function () {
        $f = seriesByCode('F');

        $this->putJson("/settings/series/{$f->id}", seriesForm(['code' => 'F', 'prefix' => 'F', 'is_default' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->postJson("/settings/series/{$f->id}/toggle")->assertUnprocessable()->assertJsonValidationErrors('domain');
        $this->deleteJson("/settings/series/{$f->id}")->assertUnprocessable()->assertJsonValidationErrors('domain');

        expect($f->refresh()->is_default)->toBeTrue()->and($f->is_active)->toBeTrue();
    });

    it('una serie desactivada no puede ser la serie por defecto', function () {
        $this->postJson('/settings/series', seriesForm(['is_default' => true, 'is_active' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    });

    it('no se cambia el tipo de una serie con documentos', function () {
        $f = seriesByCode('F');
        issueInSeries($f);

        $this->putJson("/settings/series/{$f->id}", seriesForm(['document_type' => 'quote', 'code' => 'F', 'prefix' => 'F', 'is_default' => true, 'next_number' => 2]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    });
});
