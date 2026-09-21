<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\Percentage;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Convierte una columna `numeric(5,2)` en {@see Percentage} y al revés.
 *
 * @implements CastsAttributes<Percentage|null, Percentage|int|string|null>
 */
final class PercentageCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Percentage
    {
        if ($value === null) {
            return null;
        }

        return Percentage::of((string) $value);
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $percentage = match (true) {
            $value instanceof Percentage => $value,
            is_int($value), is_string($value) => Percentage::of($value),
            default => throw new InvalidArgumentException(
                sprintf('El atributo %s espera un Percentage, un entero o una cadena decimal.', $key)
            ),
        };

        return [$key => $percentage->value];
    }
}
