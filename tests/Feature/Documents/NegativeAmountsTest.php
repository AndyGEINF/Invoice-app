<?php

declare(strict_types=1);

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Shared\Exceptions\InvalidAmount;
use App\Domain\Shared\Money;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Illuminate\Database\QueryException;

/** Añade una línea con la cantidad indicada al documento. */
function addLine(Invoice|Quote|CreditNote $document, Quantity $quantity): void
{
    $document->lines()->create([
        'position' => 1,
        'description' => 'Horas de reparación',
        'quantity' => $quantity,
        'unit_price' => UnitPrice::fromDecimal('40'),
        'vat_rate' => '21',
    ]);
}

describe('cantidades negativas', function () {
    it('Quantity::of sigue rechazando negativos', function () {
        Quantity::of('-2');
    })->throws(InvalidAmount::class);

    it('Quantity::signed admite negativos para rectificativas', function () {
        $quantity = Quantity::signed('-2');

        expect($quantity->value)->toBe('-2.0000')
            ->and($quantity->isNegative())->toBeTrue();
    });

    it('una rectificativa admite líneas con cantidad negativa', function () {
        $credit = CreditNote::factory()->create();

        addLine($credit, Quantity::signed('-2'));

        expect($credit->lines()->first()->quantity->value)->toBe('-2.0000');
    });

    it('una factura rechaza cantidades negativas en la base de datos', function () {
        addLine(Invoice::factory()->create(), Quantity::signed('-2'));
    })->throws(QueryException::class, 'Solo las rectificativas admiten importes negativos');

    it('un presupuesto rechaza cantidades negativas en la base de datos', function () {
        addLine(Quote::factory()->create(), Quantity::signed('-1'));
    })->throws(QueryException::class, 'Solo las rectificativas admiten importes negativos');
});

describe('desglose negativo', function () {
    it('una rectificativa admite bases y cuotas negativas', function () {
        $credit = CreditNote::factory()->create();

        $credit->taxes()->create([
            'tax_type' => TaxType::Vat,
            'rate' => '21',
            'base' => Money::fromCents(-8000),
            'amount' => Money::fromCents(-1680),
        ]);

        expect($credit->taxes()->first()->amount->cents)->toBe(-1680);
    });

    it('una factura rechaza cuotas negativas', function () {
        Invoice::factory()->create()->taxes()->create([
            'tax_type' => TaxType::Vat,
            'rate' => '21',
            'base' => Money::fromCents(-8000),
            'amount' => Money::fromCents(-1680),
        ]);
    })->throws(QueryException::class, 'Solo las rectificativas admiten importes negativos');

    it('el precio unitario nunca es negativo, tampoco en rectificativas', function () {
        UnitPrice::fromDecimal('-40');
    })->throws(InvalidAmount::class);
});
