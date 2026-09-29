<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Shared\Currency;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Carbon\CarbonImmutable;

/**
 * Formato español (`es-ES`) para cantidades, precios unitarios, porcentajes y
 * fechas del PDF, igual que la interfaz: coma decimal y punto de miles.
 *
 * Trabaja sobre la representación decimal en texto: nunca pasa por float
 * (constitución, principio I). Los importes en céntimos usan Money::format().
 */
final class SpanishFormat
{
    public const string DATE = 'd/m/Y';

    private const string DECIMAL_SEPARATOR = ',';

    private const string THOUSANDS_SEPARATOR = '.';

    private const int THOUSANDS_GROUP = 3;

    /** Espacio duro entre la cifra y su símbolo, como hace Intl en la interfaz. */
    private const string NBSP = "\u{A0}";

    /** Decimales mínimos de un precio unitario: 10,00 € pero 33,333 €. */
    private const int UNIT_PRICE_MIN_DECIMALS = 2;

    /**
     * "1234.5000" → "1.234,5". Quita los ceros finales del decimal y, si se
     * pide, completa hasta `$minDecimals`.
     */
    public static function decimal(string $value, int $minDecimals = 0): string
    {
        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '+-'), 2), 2, '');

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad(rtrim($fraction, '0'), $minDecimals, '0');

        $grouped = strrev(implode(self::THOUSANDS_SEPARATOR, str_split(strrev($integer), self::THOUSANDS_GROUP)));
        $formatted = $fraction === '' ? $grouped : $grouped.self::DECIMAL_SEPARATOR.$fraction;

        $isZero = trim($integer.$fraction, '0') === '';

        return $negative && ! $isZero ? '-'.$formatted : $formatted;
    }

    /** "2.0000" → "2", "1.5000" → "1,5" */
    public static function quantity(Quantity $quantity): string
    {
        return self::decimal((string) $quantity);
    }

    /** "33.333" → "33,333 €", "10.000" → "10,00 €" */
    public static function unitPrice(UnitPrice $price, Currency $currency): string
    {
        return self::decimal($price->toDecimalString(), self::UNIT_PRICE_MIN_DECIMALS).self::NBSP.$currency->symbol();
    }

    /** "21.00" → "21 %", "5.20" → "5,2 %" */
    public static function percentage(Percentage $percentage): string
    {
        return self::decimal((string) $percentage).self::NBSP.'%';
    }

    public static function date(?CarbonImmutable $date): ?string
    {
        return $date?->format(self::DATE);
    }
}
