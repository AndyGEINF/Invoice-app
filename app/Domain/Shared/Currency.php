<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Monedas admitidas.
 *
 * La fase 1 factura solo en euros; USD existe para poder probar que los
 * importes de monedas distintas nunca se mezclan.
 */
enum Currency: string
{
    /** Decimales con los que se representa la moneda. */
    public const int DECIMALS = 2;

    /** Unidades menores por unidad de moneda: 100 céntimos por euro. */
    public const int MINOR_UNITS = 100;

    case EUR = 'EUR';
    case USD = 'USD';

    public function decimals(): int
    {
        return self::DECIMALS;
    }

    public function minorUnits(): int
    {
        return self::MINOR_UNITS;
    }

    public static function default(): self
    {
        return self::EUR;
    }
}
