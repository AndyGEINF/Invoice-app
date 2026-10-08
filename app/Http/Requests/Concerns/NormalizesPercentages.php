<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Shared\Percentage;
use Throwable;

/**
 * Los porcentajes se comparan como texto con las listas de config/invoice.php:
 * "21", "21,0" y "21.0" se normalizan a "21.00" antes de validar.
 */
trait NormalizesPercentages
{
    /** Si no es un porcentaje válido se deja tal cual para que lo rechacen las reglas. */
    private static function normalizePercentage(mixed $value): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        try {
            return (string) Percentage::of(str_replace(',', '.', (string) $value));
        } catch (Throwable) {
            return $value;
        }
    }
}
