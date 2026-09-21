<?php

declare(strict_types=1);

use App\Domain\Shared\DocumentNumber;

describe('DocumentNumber', function () {
    it('compone el número completo', function (string $prefix, int $number, int $padding, ?int $year, string $expected) {
        expect(DocumentNumber::of($prefix, $number, $padding, $year)->full())->toBe($expected);
    })->with([
        'factura del año en curso' => ['F', 1, 4, 2026, 'F2026-0001'],
        'serie con prefijo largo' => ['TALLER', 100, 4, 2026, 'TALLER2026-0100'],
        'serie sin reinicio anual' => ['R', 7, 4, null, 'R-0007'],
        'relleno de dos cifras' => ['P', 5, 2, 2026, 'P2026-05'],
        'número mayor que el relleno' => ['F', 12345, 4, 2026, 'F2026-12345'],
        'relleno máximo' => ['F', 1, 8, null, 'F-00000001'],
    ]);

    it('pasa el prefijo a mayúsculas y quita espacios', function () {
        expect(DocumentNumber::of(' taller ', 1, 4, 2026)->full())->toBe('TALLER2026-0001');
    });

    it('devuelve solo el correlativo con ceros', function () {
        expect(DocumentNumber::of('F', 42, 4, 2026)->padded())->toBe('0042');
    });

    it('avanza al siguiente número', function () {
        $siguiente = DocumentNumber::of('F', 41, 4, 2026)->next();

        expect($siguiente->number)->toBe(42)
            ->and($siguiente->full())->toBe('F2026-0042');
    });

    it('compara por número completo', function () {
        expect(DocumentNumber::of('F', 1, 4, 2026)->equals(DocumentNumber::of('F', 1, 4, 2026)))->toBeTrue()
            ->and(DocumentNumber::of('F', 1, 4, 2026)->equals(DocumentNumber::of('F', 2, 4, 2026)))->toBeFalse();
    });

    it('no admite números ni rellenos fuera de rango', function (int $number, int $padding) {
        DocumentNumber::of('F', $number, $padding);
    })->with([
        'número cero' => [0, 4],
        'número negativo' => [-1, 4],
        'relleno cero' => [1, 0],
        'relleno demasiado grande' => [1, 9],
    ])->throws(InvalidArgumentException::class);
});
