<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidAmount;
use Stringable;

/**
 * Cantidad de una línea, con cuatro decimales.
 *
 * Se permite el cero (línea informativa o artículo gratuito); los importes
 * negativos se resuelven con una rectificativa, nunca con cantidades negativas.
 */
final readonly class Quantity implements Stringable
{
    public const int SCALE = 4;

    private function __construct(public string $value) {}

    public static function of(Decimal|string|int $quantity): self
    {
        $decimal = $quantity instanceof Decimal ? $quantity : Decimal::of($quantity);

        if ($decimal->isNegative()) {
            throw InvalidAmount::negativeQuantity($decimal->value);
        }

        return new self(Rounding::halfUp($decimal, self::SCALE));
    }

    public static function zero(): self
    {
        return new self(Rounding::halfUp(Decimal::zero(), self::SCALE));
    }

    public function toDecimal(): Decimal
    {
        return Decimal::of($this->value);
    }

    public function isZero(): bool
    {
        return $this->toDecimal()->isZero();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
