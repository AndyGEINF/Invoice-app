<?php

declare(strict_types=1);

use App\Domain\Shared\Currency;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\Exceptions\CurrencyMismatch;
use App\Domain\Shared\Exceptions\InvalidAmount;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;

/** Espacio duro que ICU pone antes del símbolo de euro. */
const NBSP = "\u{00A0}";

describe('Money', function () {
    it('se crea desde céntimos', function () {
        $money = Money::fromCents(19250, 'EUR');

        expect($money->cents)->toBe(19250)
            ->and($money->currency)->toBe(Currency::EUR)
            ->and($money->toDecimal()->value)->toBe('192.5000000');
    });

    it('se crea desde una cadena decimal redondeando a céntimos', function () {
        expect(Money::fromDecimal('192.50')->cents)->toBe(19250)
            ->and(Money::fromDecimal('10.005')->cents)->toBe(1001)
            ->and(Money::fromDecimal(Decimal::of('99.999'))->cents)->toBe(10000);
    });

    it('suma y resta', function () {
        $base = Money::fromCents(15000);

        expect($base->plus(Money::fromCents(3150))->cents)->toBe(18150)
            ->and($base->minus(Money::fromCents(5000))->cents)->toBe(10000)
            ->and($base->cents)->toBe(15000);
    });

    it('no mezcla monedas distintas', function () {
        Money::fromCents(100, 'EUR')->plus(Money::fromCents(100, 'USD'));
    })->throws(CurrencyMismatch::class);

    it('aplica un porcentaje redondeando a céntimos', function (int $cents, string $rate, int $expected) {
        expect(Money::fromCents($cents)->multiplyBy(Percentage::of($rate))->cents)->toBe($expected);
    })->with([
        'IVA 21 sobre 150,00' => [15000, '21.00', 3150],
        'IVA 10 sobre 10,00' => [1000, '10.00', 100],
        'recargo 5,20 sobre 100,00' => [10000, '5.20', 520],
        'IRPF 15 sobre 1.000,00' => [100000, '15.00', 15000],
        'IVA 21 sobre 1,00 redondea hacia arriba' => [100, '21.00', 21],
        'IVA 21 sobre 10,01' => [1001, '21.00', 210],
        'cero por ciento' => [15000, '0.00', 0],
    ]);

    it('multiplica por una cantidad', function () {
        expect(Money::fromCents(4000)->multiplyByQuantity(Quantity::of('3'))->cents)->toBe(12000)
            ->and(Money::fromCents(3333)->multiplyByQuantity(Quantity::of('0.5'))->cents)->toBe(1667);
    });

    it('compara importes', function () {
        $cien = Money::fromCents(10000);

        expect($cien->gte(Money::fromCents(10000)))->toBeTrue()
            ->and($cien->gte(Money::fromCents(9999)))->toBeTrue()
            ->and($cien->gte(Money::fromCents(10001)))->toBeFalse()
            ->and($cien->isZero())->toBeFalse()
            ->and(Money::zero()->isZero())->toBeTrue()
            ->and($cien->equals(Money::fromCents(10000)))->toBeTrue();
    });

    it('formatea en español', function () {
        expect(Money::fromCents(19250)->format())->toBe('192,50'.NBSP.'€')
            ->and(Money::fromCents(123456)->format())->toBe('1.234,56'.NBSP.'€')
            ->and(Money::zero()->format())->toBe('0,00'.NBSP.'€')
            ->and(Money::fromCents(-500)->format())->toBe('-5,00'.NBSP.'€');
    });

    it('rechaza monedas desconocidas', function () {
        Money::fromCents(100, 'XXX');
    })->throws(ValueError::class);
});

describe('UnitPrice', function () {
    it('guarda milésimas', function () {
        expect(UnitPrice::fromDecimal('33.333')->thousandths)->toBe(33333)
            ->and(UnitPrice::fromDecimal('40')->thousandths)->toBe(40000)
            ->and(UnitPrice::fromThousandths(50005)->toDecimal()->value)->toBe('50.0050000');
    });

    it('multiplica por una cantidad sin redondear', function () {
        expect(UnitPrice::fromDecimal('33.333')->times(Quantity::of('3'))->value)->toBe('99.9990000')
            ->and(UnitPrice::fromDecimal('0.333')->times(Quantity::of('1'))->value)->toBe('0.3330000');
    });

    it('no admite precios negativos', function () {
        UnitPrice::fromDecimal('-0.001');
    })->throws(InvalidAmount::class);
});

describe('Quantity', function () {
    it('admite hasta cuatro decimales y el cero', function () {
        expect(Quantity::of('3')->value)->toBe('3.0000')
            ->and(Quantity::of('1.5')->value)->toBe('1.5000')
            ->and(Quantity::of('0.0001')->value)->toBe('0.0001')
            ->and(Quantity::of('0')->isZero())->toBeTrue();
    });

    it('no admite cantidades negativas', function () {
        Quantity::of('-1');
    })->throws(InvalidAmount::class);
});

describe('Percentage', function () {
    it('normaliza a dos decimales', function () {
        expect(Percentage::of('21')->value)->toBe('21.00')
            ->and(Percentage::of('5.2')->value)->toBe('5.20')
            ->and(Percentage::zero()->isZero())->toBeTrue();
    });

    it('calcula su parte de un decimal y su complemento', function () {
        expect(Percentage::of('21.00')->of(Decimal::of('150'))->value)->toBe('31.5000000')
            ->and(Percentage::of('10.00')->complement()->value)->toBe('90.00')
            ->and(Percentage::of('10.00')->applyDiscountTo(Decimal::of('100'))->value)->toBe('90.0000000');
    });

    it('solo admite valores entre 0 y 100', function (string $value) {
        Percentage::of($value);
    })->with(['-0.01', '100.01', '101'])->throws(InvalidAmount::class);
});
