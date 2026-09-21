<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\Currency;
use App\Domain\Shared\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Convierte una columna `bigint` de céntimos en {@see Money} y al revés.
 *
 * La moneda sale del atributo `currency` del propio modelo cuando existe, de
 * modo que un documento en otra divisa no mezcle importes por accidente.
 *
 * @implements CastsAttributes<Money|null, Money|int|string|null>
 */
final class MoneyCast implements CastsAttributes
{
    /** Atributo del modelo que indica la moneda del importe. */
    public const string CURRENCY_ATTRIBUTE = 'currency';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::fromCents((int) $value, $this->currencyFor($attributes));
    }

    /** @return array<string, int|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $money = match (true) {
            $value instanceof Money => $value,
            is_int($value) => Money::fromCents($value, $this->currencyFor($attributes)),
            is_string($value) => Money::fromDecimal($value, $this->currencyFor($attributes)),
            default => throw new InvalidArgumentException(
                sprintf('El atributo %s espera un Money, un entero de céntimos o una cadena decimal.', $key)
            ),
        };

        return [$key => $money->cents];
    }

    /** @param array<string, mixed> $attributes */
    private function currencyFor(array $attributes): Currency
    {
        $currency = $attributes[self::CURRENCY_ATTRIBUTE] ?? null;

        if ($currency instanceof Currency) {
            return $currency;
        }

        if (is_string($currency) && $currency !== '') {
            return Currency::from($currency);
        }

        return Currency::default();
    }
}
