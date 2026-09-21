<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\UnitPrice;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Convierte una columna `bigint` de milésimas en {@see UnitPrice} y al revés.
 *
 * @implements CastsAttributes<UnitPrice|null, UnitPrice|int|string|null>
 */
final class UnitPriceCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?UnitPrice
    {
        if ($value === null) {
            return null;
        }

        return UnitPrice::fromThousandths((int) $value);
    }

    /** @return array<string, int|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $price = match (true) {
            $value instanceof UnitPrice => $value,
            is_int($value) => UnitPrice::fromThousandths($value),
            is_string($value) => UnitPrice::fromDecimal($value),
            default => throw new InvalidArgumentException(
                sprintf('El atributo %s espera un UnitPrice, un entero de milésimas o una cadena decimal.', $key)
            ),
        };

        return [$key => $price->thousandths];
    }
}
