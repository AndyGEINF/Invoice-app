<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Domain\Customers\Customer;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Money;
use App\Domain\Shared\Quantity;
use App\Infrastructure\Time\FrozenClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * SC-004: una factura emitida no se modifica ni se borra por ninguna vía. Dos
 * barreras: el modelo (observadores, mensaje claro) y PostgreSQL (triggers,
 * también frente a SQL directo). Solo cambian status, cobro, envío, notas
 * internas y los campos del PDF.
 */

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-09-24 10:30:00'));
    Series::factory()->invoices()->create();
    configuredIssuer();

    $draft = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
        'customer_id' => Customer::factory()->create()->id,
        'lines' => [['position' => 1, 'description' => 'Consultoría', 'quantity' => '2', 'unit_price' => '100', 'vat_rate' => '21']],
    ]));

    $this->invoice = app(IssueInvoice::class)($draft)->invoice->refresh();
});

/** SQL directo dentro de un savepoint: el rechazo del trigger no rompe la transacción del test. */
function rawSql(Closure $statement): void
{
    DB::transaction($statement);
}

describe('modelo', function () {
    it('no deja cambiar importes ni cliente', function () {
        expect(fn () => $this->invoice->update(['total' => Money::fromCents(1)]))->toThrow(DocumentIsImmutable::class)
            ->and(fn () => $this->invoice->update(['customer_id' => Customer::factory()->create()->id]))->toThrow(DocumentIsImmutable::class)
            ->and($this->invoice->refresh()->total->cents)->toBe(24200);
    });

    it('no deja borrarla', function () {
        expect(fn () => $this->invoice->delete())->toThrow(DocumentIsImmutable::class)
            ->and(Invoice::query()->whereKey($this->invoice->id)->exists())->toBeTrue();
    });

    it('no deja añadir, editar ni borrar líneas', function () {
        $line = $this->invoice->lines()->sole();

        expect(fn () => $line->update(['quantity' => Quantity::of('5')]))->toThrow(DocumentIsImmutable::class)
            ->and(fn () => $line->delete())->toThrow(DocumentIsImmutable::class)
            ->and(fn () => DocumentLine::factory()->for($this->invoice, 'document')->create(['position' => 2]))->toThrow(DocumentIsImmutable::class)
            ->and($this->invoice->lines()->count())->toBe(1);
    });

    it('no deja tocar el desglose de impuestos', function () {
        $tax = $this->invoice->taxes()->sole();

        expect(fn () => $tax->update(['amount' => Money::fromCents(1)]))->toThrow(DocumentIsImmutable::class)
            ->and(fn () => $tax->delete())->toThrow(DocumentIsImmutable::class);
    });

    it('permite marcar envío, cobro y notas internas', function () {
        $this->invoice->update([
            'status' => DocumentStatus::Sent,
            'sent_at' => now(),
            'paid_at' => '2026-09-30',
            'paid_note' => 'Transferencia recibida',
            'internal_notes' => 'Cliente puntual',
        ]);

        $invoice = $this->invoice->refresh();

        expect($invoice->status)->toBe(DocumentStatus::Sent)
            ->and($invoice->paid_at->toDateString())->toBe('2026-09-30')
            ->and($invoice->internal_notes)->toBe('Cliente puntual')
            ->and($invoice->total->cents)->toBe(24200);
    });

    it('no deja volver a borrador', function () {
        $this->invoice->update(['status' => DocumentStatus::Draft]);
    })->throws(DocumentIsImmutable::class);
});

describe('base de datos (SQL directo)', function () {
    it('el trigger rechaza cambiar el total', function () {
        expect(fn () => rawSql(fn () => DB::table('documents')->where('id', $this->invoice->id)->update(['total' => 1])))
            ->toThrow(QueryException::class)
            ->and($this->invoice->refresh()->total->cents)->toBe(24200);
    });

    it('el trigger rechaza cambiar el cliente', function () {
        $other = Customer::factory()->create();

        expect(fn () => rawSql(fn () => DB::statement('UPDATE documents SET customer_id = ? WHERE id = ?', [$other->id, $this->invoice->id])))
            ->toThrow(QueryException::class)
            ->and($this->invoice->refresh()->customer_id)->not->toBe($other->id);
    });

    it('el trigger rechaza borrarla', function () {
        expect(fn () => rawSql(fn () => DB::table('documents')->where('id', $this->invoice->id)->delete()))
            ->toThrow(QueryException::class)
            ->and(Invoice::query()->whereKey($this->invoice->id)->exists())->toBeTrue();
    });

    it('el trigger rechaza cambiar sus líneas', function () {
        expect(fn () => rawSql(fn () => DB::table('document_lines')->where('document_id', $this->invoice->id)->update(['unit_price' => 1])))
            ->toThrow(QueryException::class);
    });
});

describe('web', function () {
    it('PUT de una factura emitida → 422 con el motivo', function () {
        $this->putJson("/invoices/{$this->invoice->id}", [
            'customer_id' => $this->invoice->customer_id,
            'lines' => [['position' => 1, 'description' => 'Otra cosa', 'quantity' => '1', 'unit_price' => '1', 'vat_rate' => '21']],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.domain.0', 'El documento F2026-0001 ya está emitido.');

        expect($this->invoice->refresh()->total->cents)->toBe(24200);
    });

    it('DELETE de una factura emitida → 422 y sigue existiendo', function () {
        $this->deleteJson("/invoices/{$this->invoice->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        expect(Invoice::query()->whereKey($this->invoice->id)->exists())->toBeTrue();
    });

    it('emitirla otra vez → 422', function () {
        $this->postJson("/invoices/{$this->invoice->id}/issue")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    });

    it('sus notas internas sí se pueden cambiar', function () {
        $this->patch("/invoices/{$this->invoice->id}/internal-notes", ['internal_notes' => 'Llamar el lunes'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($this->invoice->refresh()->internal_notes)->toBe('Llamar el lunes');
    });
});
