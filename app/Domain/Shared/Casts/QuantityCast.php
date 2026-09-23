<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Convierte una columna `numeric(12,4)` en {@see Quantity} y al revés.
 *
 * @implements CastsAttributes<Quantity|null, Quantity|int|string|null>
 */
final class QuantityCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        if ($value === null) {
            return null;
        }

        // Con signo: las líneas de rectificativas pueden ser negativas. La regla
        // de que solo ellas lo sean la aplican los casos de uso y un trigger.
        return Quantity::signed((string) $value);
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $quantity = match (true) {
            $value instanceof Quantity => $value,
            is_int($value), is_string($value) => Quantity::signed($value),
            default => throw new InvalidArgumentException(
                sprintf('El atributo %s espera una Quantity, un entero o una cadena decimal.', $key)
            ),
        };

        return [$key => $quantity->value];
    }
}
