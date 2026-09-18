<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidDecimal;

/**
 * Redondeo fiscal.
 *
 * La AEAT valida la cuota de cada grupo impositivo como base redondeada por el
 * tipo, con redondeo a la mitad hacia arriba (alejándose del cero). Se hace
 * siempre sobre la cadena decimal exacta, nunca sobre un `float`: con float,
 * 10,005 se almacena como 10,00499… y redondearía hacia abajo.
 */
final class Rounding
{
    /** Escala de los importes en unidades de moneda: dos decimales. */
    public const int CENTS_SCALE = Currency::DECIMALS;

    /** Escala de los importes ya expresados en unidades menores: sin decimales. */
    public const int MINOR_UNIT_SCALE = 0;

    private function __construct() {}

    /**
     * Redondea a `$scale` decimales con la mitad hacia arriba.
     *
     * El resultado siempre trae exactamente `$scale` decimales, para poder
     * compararlo y guardarlo tal cual.
     */
    public static function halfUp(Decimal|string $value, int $scale = self::CENTS_SCALE): string
    {
        if ($scale < self::MINOR_UNIT_SCALE) {
            throw InvalidDecimal::negativeScale($scale);
        }

        $decimal = $value instanceof Decimal ? $value : Decimal::of($value);

        $rounded = bcround($decimal->value, $scale, \RoundingMode::HalfAwayFromZero);

        // bcround puede devolver menos decimales de los pedidos (p. ej. "42").
        return self::stripNegativeZero(bcadd($rounded, Decimal::ZERO, $scale));
    }

    /** Convierte "-0.00" en "0.00": el cero no lleva signo en una factura. */
    public static function stripNegativeZero(string $value): string
    {
        if (str_starts_with($value, '-') && preg_match('/^-0(\.0+)?$/', $value) === 1) {
            return substr($value, 1);
        }

        return $value;
    }
}
