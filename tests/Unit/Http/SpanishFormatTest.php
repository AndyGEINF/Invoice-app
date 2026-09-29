<?php

declare(strict_types=1);

use App\Domain\Shared\Currency;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use App\Http\Presenters\SpanishFormat;
use Carbon\CarbonImmutable;

describe('SpanishFormat', function () {
    it('usa coma decimal y punto de miles sin ceros sobrantes', function (string $value, int $min, string $expected) {
        expect(SpanishFormat::decimal($value, $min))->toBe($expected);
    })->with([
        ['1234.5000', 0, '1.234,5'],
        ['1234567.8900', 0, '1.234.567,89'],
        ['0.0000', 0, '0'],
        ['-12.5000', 0, '-12,5'],
        ['-0.0000', 0, '0'],
        ['10.000', 2, '10,00'],
        ['33.333', 2, '33,333'],
        ['999', 0, '999'],
    ]);

    it('formatea cantidades, precios, porcentajes y fechas como la interfaz', function () {
        expect(SpanishFormat::quantity(Quantity::of('2')))->toBe('2')
            ->and(SpanishFormat::quantity(Quantity::of('12.5')))->toBe('12,5')
            ->and(SpanishFormat::unitPrice(UnitPrice::fromDecimal('45'), Currency::EUR))->toBe("45,00\u{A0}€")
            ->and(SpanishFormat::unitPrice(UnitPrice::fromDecimal('0.333'), Currency::EUR))->toBe("0,333\u{A0}€")
            ->and(SpanishFormat::percentage(Percentage::of('21')))->toBe("21\u{A0}%")
            ->and(SpanishFormat::percentage(Percentage::of('5.2')))->toBe("5,2\u{A0}%")
            ->and(SpanishFormat::date(CarbonImmutable::parse('2026-09-24')))->toBe('24/09/2026')
            ->and(SpanishFormat::date(null))->toBeNull();
    });
});
