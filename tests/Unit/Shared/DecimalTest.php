<?php

// Modo estricto: Decimal::of() debe rechazar un float en vez de convertirlo.
declare(strict_types=1);

use App\Domain\Shared\Decimal;
use App\Domain\Shared\Exceptions\InvalidDecimal;
use App\Domain\Shared\Rounding;

describe('Decimal', function () {
    it('normaliza a escala 7', function () {
        expect(Decimal::of('10')->value)->toBe('10.0000000')
            ->and(Decimal::of('33.333')->value)->toBe('33.3330000')
            ->and(Decimal::of(5)->value)->toBe('5.0000000')
            ->and(Decimal::of('-0.5')->value)->toBe('-0.5000000');
    });

    it('rechaza entradas que no son decimales exactos', function (mixed $input) {
        Decimal::of($input);
    })->with([
        'texto' => ['abc'],
        'coma decimal' => ['1,5'],
        'notación científica' => ['1e3'],
        'vacío' => [''],
        'dos puntos' => ['1.2.3'],
    ])->throws(InvalidDecimal::class);

    it('no acepta float', function () {
        Decimal::of(0.1);
    })->throws(TypeError::class);

    it('suma, resta y multiplica sin perder precisión', function () {
        expect(Decimal::of('0.1')->add(Decimal::of('0.2'))->value)->toBe('0.3000000')
            ->and(Decimal::of('150.004')->sub(Decimal::of('0.004'))->value)->toBe('150.0000000')
            ->and(Decimal::of('3')->mul(Decimal::of('33.333'))->value)->toBe('99.9990000')
            ->and(Decimal::of('1.0000001')->mul(Decimal::of('3'))->value)->toBe('3.0000003');
    });

    it('divide truncando a escala 7', function () {
        expect(Decimal::of('10')->div(Decimal::of('3'))->value)->toBe('3.3333333')
            ->and(Decimal::of('21')->div(Decimal::of('100'))->value)->toBe('0.2100000');
    });

    it('no permite dividir por cero', function () {
        Decimal::of('1')->div(Decimal::of('0'));
    })->throws(DivisionByZeroError::class);

    it('compara valores', function () {
        expect(Decimal::of('1.5')->compare(Decimal::of('1.50')))->toBe(0)
            ->and(Decimal::of('1.4')->compare(Decimal::of('1.5')))->toBe(-1)
            ->and(Decimal::of('2')->compare(Decimal::of('1.9999999')))->toBe(1)
            ->and(Decimal::of('0.0000000')->isZero())->toBeTrue()
            ->and(Decimal::of('-0.0000001')->isNegative())->toBeTrue();
    });

    it('es inmutable', function () {
        $a = Decimal::of('1');
        $a->add(Decimal::of('1'));

        expect($a->value)->toBe('1.0000000');
    });
});

describe('Rounding::halfUp', function () {
    it('redondea a la mitad hacia arriba sobre la cadena exacta', function (string $input, int $scale, string $expected) {
        expect(Rounding::halfUp($input, $scale))->toBe($expected);
    })->with([
        'mitad exacta sube (falla con float)' => ['10.005', 2, '10.01'],
        'por debajo de la mitad baja' => ['10.0049999', 2, '10.00'],
        'suma de bases del vector V3' => ['0.999', 2, '1.00'],
        'cuota del vector V9' => ['2.1021', 2, '2.10'],
        'ya redondeado' => ['150.00', 2, '150.00'],
        'entero sin decimales' => ['42', 2, '42.00'],
        'escala cero' => ['2.5', 0, '3'],
        'negativo se aleja de cero' => ['-10.005', 2, '-10.01'],
        'negativo que redondea a cero no deja signo' => ['-0.004', 2, '0.00'],
        'escala 7 de Decimal' => ['99.9990000', 2, '100.00'],
    ]);

    it('acepta un Decimal', function () {
        expect(Rounding::halfUp(Decimal::of('150.004'), 2))->toBe('150.00');
    });
});
