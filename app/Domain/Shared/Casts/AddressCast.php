<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\Address;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Convierte una columna `jsonb` en {@see Address} y al revés.
 *
 * @implements CastsAttributes<Address|null, Address|array<string, string|null>|string|null>
 */
final class AddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Address
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf('El atributo %s no contiene una dirección.', $key));
        }

        return Address::fromArray($value);
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $address = match (true) {
            $value instanceof Address => $value,
            is_array($value) => Address::fromArray($value),
            default => throw new InvalidArgumentException(
                sprintf('El atributo %s espera una Address o un array con sus claves.', $key)
            ),
        };

        return [$key => json_encode($address->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }
}
