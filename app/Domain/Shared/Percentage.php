<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidAmount;
use Stringable;

/**
 * Porcentaje con dos decimales: tipos de IVA (21,00), recargo (5,20),
 * retención de IRPF (15,00) y descuentos.
 */
final readonly class Percentage implements Stringable
{
    public const int SCALE = 2;

    /** Un porcentaje va de 0 a 100. */
    public const string MIN = '0';

    public const string MAX = '100';

    private function __construct(public string $value) {}

    public static function of(Decimal|string|int $percentage): self
    {
        $decimal = $percentage instanceof Decimal ? $percentage : Decimal::of($percentage);

        if ($decimal->isNegative() || $decimal->compare(Decimal::of(self::MAX)) > 0) {
            throw InvalidAmount::percentageOutOfRange($decimal->value);
        }

        return new self(Rounding::halfUp($decimal, self::SCALE));
    }

    public static function zero(): self
    {
        return new self(Rounding::halfUp(Decimal::zero(), self::SCALE));
    }

    /** Parte que representa este porcentaje de un decimal, sin redondear. */
    public function applyTo(Decimal $amount): Decimal
    {
        return $amount->mul($this->toDecimal())->div(Decimal::of(self::MAX));
    }

    /** Lo que queda tras aplicar este porcentaje como descuento, sin redondear. */
    public function applyDiscountTo(Decimal $amount): Decimal
    {
        return $amount->sub($this->applyTo($amount));
    }

    /** 100 − este porcentaje. */
    public function complement(): self
    {
        return self::of(Decimal::of(self::MAX)->sub($this->toDecimal()));
    }

    public function toDecimal(): Decimal
    {
        return Decimal::of($this->value);
    }

    public function isZero(): bool
    {
        return $this->toDecimal()->isZero();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
