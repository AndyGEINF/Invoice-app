<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidAmount;
use Stringable;

/**
 * Cantidad de una línea, con cuatro decimales.
 *
 * En facturas y presupuestos es cero o positiva ({@see of()}); el cero sirve
 * para líneas informativas o artículos gratuitos. Solo una rectificativa admite
 * cantidades negativas ({@see signed()}), para devolver importes al cliente: el
 * signo lo lleva la cantidad, nunca el precio.
 */
final readonly class Quantity implements Stringable
{
    public const int SCALE = 4;

    private function __construct(public string $value) {}

    /** Cantidad de factura o presupuesto: nunca negativa. */
    public static function of(Decimal|string|int $quantity): self
    {
        $decimal = self::toDecimalValue($quantity);

        if ($decimal->isNegative()) {
            throw InvalidAmount::negativeQuantity($decimal->value);
        }

        return new self(Rounding::halfUp($decimal, self::SCALE));
    }

    /** Cantidad con signo, solo para líneas de rectificativas. */
    public static function signed(Decimal|string|int $quantity): self
    {
        return new self(Rounding::halfUp(self::toDecimalValue($quantity), self::SCALE));
    }

    public function isNegative(): bool
    {
        return $this->toDecimal()->isNegative();
    }

    private static function toDecimalValue(Decimal|string|int $quantity): Decimal
    {
        return $quantity instanceof Decimal ? $quantity : Decimal::of($quantity);
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
