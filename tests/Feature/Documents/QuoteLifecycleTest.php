<?php

declare(strict_types=1);

use App\Domain\Customers\Customer;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-10-11 10:00:00'));
    $this->travelTo('2026-10-11 10:00:00');

    Series::factory()->quotes()->create();
    Series::factory()->invoices()->create();
    configuredIssuer();
    $this->customer = Customer::factory()->create(['legal_name' => 'Cliente Original SL']);
});

/** Presupuesto en borrador creado por la web, como lo haría el usuario. */
function quoteDraft(array $overrides = []): Quote
{
    test()->post('/quotes', [
        'customer_id' => test()->customer->id,
        'lines' => [['position' => 1, 'description' => 'Diseño web', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate' => '21']],
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Quote::query()->latest('created_at')->firstOrFail();
}

function transition(Quote $quote, string $action): TestResponse
{
    return test()->from("/quotes/{$quote->id}")->post("/quotes/{$quote->id}/{$action}");
}

describe('borrador', function () {
    it('nace sin número, con fecha de hoy y válido 30 días', function () {
        $quote = quoteDraft();

        expect($quote->status)->toBe(QuoteStatus::Draft)
            ->and($quote->full_number)->toBeNull()
            ->and($quote->issue_date->toDateString())->toBe('2026-10-11')
            ->and($quote->valid_until->toDateString())->toBe('2026-11-10')
            ->and($quote->due_date)->toBeNull();
    });

    it('respeta una validez elegida', function () {
        expect(quoteDraft(['valid_until' => '2026-12-31'])->valid_until->toDateString())->toBe('2026-12-31');
    });
});

describe('enviar', function () {
    it('asigna número de la serie P y congela emisor y cliente', function () {
        $quote = quoteDraft();

        transition($quote, 'send')->assertRedirect("/quotes/{$quote->id}");

        $quote->refresh();
        expect($quote->status)->toBe(QuoteStatus::Sent)
            ->and($quote->full_number)->toBe('P2026-0001')
            ->and($quote->issuer_snapshot['legal_name'])->toBe(Issuer::current()->legalName())
            ->and($quote->customer_snapshot['legal_name'])->toBe('Cliente Original SL')
            ->and($quote->events()->pluck('event')->all())->toContain(DocumentEventType::QuoteSent);
    });

    it('numera correlativamente', function () {
        transition(quoteDraft(), 'send');
        $second = quoteDraft();
        transition($second, 'send');

        expect($second->refresh()->full_number)->toBe('P2026-0002');
    });

    it('con el emisor incompleto no se envía', function () {
        $quote = quoteDraft();
        Issuer::current()->update(['logo_path' => null]);

        transition($quote, 'send')->assertSessionHasErrors('domain');

        expect($quote->refresh()->status)->toBe(QuoteStatus::Draft)
            ->and($quote->full_number)->toBeNull();
    });

    it('un presupuesto sin líneas no se envía', function () {
        $quote = quoteDraft();
        $this->put("/quotes/{$quote->id}", ['customer_id' => $this->customer->id, 'lines' => []]);

        transition($quote, 'send')->assertSessionHasErrors('domain');
    });

    it('no se envía dos veces', function () {
        $quote = quoteDraft();
        transition($quote, 'send');

        transition($quote, 'send')->assertSessionHasErrors('domain');
        expect($quote->refresh()->full_number)->toBe('P2026-0001');
    });
});

describe('aceptar y rechazar', function () {
    it('un enviado se puede aceptar', function () {
        $quote = quoteDraft();
        transition($quote, 'send');

        transition($quote, 'accept')->assertSessionHasNoErrors();

        expect($quote->refresh()->status)->toBe(QuoteStatus::Accepted);
    });

    it('un enviado se puede rechazar', function () {
        $quote = quoteDraft();
        transition($quote, 'send');

        transition($quote, 'reject')->assertSessionHasNoErrors();

        expect($quote->refresh()->status)->toBe(QuoteStatus::Rejected);
    });

    it('un borrador no se acepta sin enviarlo antes', function () {
        $quote = quoteDraft();

        transition($quote, 'accept')->assertSessionHasErrors('domain');
        expect($quote->refresh()->status)->toBe(QuoteStatus::Draft);
    });

    it('caducado: lo indica y no se puede aceptar', function () {
        $quote = quoteDraft(['valid_until' => '2026-10-20']);
        transition($quote, 'send');
        $this->travelTo('2026-10-21 09:00:00');
        app()->instance(Clock::class, new FrozenClock('2026-10-21 09:00:00'));

        inertiaGet("/quotes/{$quote->id}")
            ->assertJsonPath('props.document.is_expired', true)
            ->assertJsonPath('props.can.quote_accept', false);

        transition($quote, 'accept')->assertSessionHasErrors('domain');
        expect($quote->refresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('caducado: el listado lo marca en su fila', function () {
        $quote = quoteDraft(['valid_until' => '2026-10-20']);
        transition($quote, 'send');
        app()->instance(Clock::class, new FrozenClock('2026-10-21 09:00:00'));

        inertiaGet('/quotes')
            ->assertJsonPath('props.documents.data.0.is_expired', true)
            ->assertJsonPath('props.alerts.overdue.count', 1);
    });
});

describe('edición y borrado', function () {
    it('editar un enviado conserva el número y vuelve a congelar el cliente', function () {
        $quote = quoteDraft();
        transition($quote, 'send');
        $this->customer->update(['legal_name' => 'Cliente Renombrado SL']);

        $this->put("/quotes/{$quote->id}", [
            'customer_id' => $this->customer->id,
            'lines' => [['position' => 1, 'description' => 'Diseño web y logo', 'quantity' => '1', 'unit_price' => '1200', 'vat_rate' => '21']],
        ])->assertSessionHasNoErrors();

        $quote->refresh();
        expect($quote->full_number)->toBe('P2026-0001')
            ->and($quote->status)->toBe(QuoteStatus::Sent)
            ->and($quote->customer_snapshot['legal_name'])->toBe('Cliente Renombrado SL')
            ->and($quote->total->cents)->toBe(145200);
    });

    it('se puede borrar en borrador, enviado, aceptado o rechazado', function (array $steps) {
        $quote = quoteDraft();
        foreach ($steps as $step) {
            transition($quote, $step);
        }

        $this->delete("/quotes/{$quote->id}")->assertRedirect('/quotes');

        expect(Quote::query()->find($quote->id))->toBeNull();
    })->with([
        'borrador' => [[]],
        'enviado' => [['send']],
        'aceptado' => [['send', 'accept']],
        'rechazado' => [['send', 'reject']],
    ]);

    it('convertido: no se edita ni se borra', function () {
        $quote = quoteDraft();
        transition($quote, 'send');
        transition($quote, 'accept');
        $this->post("/quotes/{$quote->id}/convert");

        $this->from("/quotes/{$quote->id}")
            ->put("/quotes/{$quote->id}", ['customer_id' => $this->customer->id, 'lines' => []])
            ->assertSessionHasErrors('domain');
        $this->from("/quotes/{$quote->id}")->delete("/quotes/{$quote->id}")->assertSessionHasErrors('domain');

        expect(Quote::query()->find($quote->id)?->status)->toBe(QuoteStatus::Converted);
    });
});

describe('rutas', function () {
    it('un presupuesto no se emite como factura', function () {
        $quote = quoteDraft();

        $this->post("/quotes/{$quote->id}/issue")->assertNotFound();
    });

    it('las acciones de presupuesto no existen para facturas', function () {
        $quote = quoteDraft();

        $this->post("/invoices/{$quote->id}/send")->assertNotFound();
    });
});
