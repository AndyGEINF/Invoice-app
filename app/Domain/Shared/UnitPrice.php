<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidAmount;
use Stringable;

/**
 * Precio unitario en milésimas de la unidad de moneda.
 *
 * Tres decimales porque un precio por hora puede ser 33,333 €: con dos
 * decimales, tres horas darían 99,99 € en vez de 100,00 € (decisión D2).
 */
final readonly class UnitPrice implements Stringable
{
    public const int SCALE = 3;

    /** Milésimas por unidad de moneda. */
    public const int UNITS_PER_WHOLE = 1000;

    private function __construct(public int $thousandths) {}

    public static function fromThousandths(int $thousandths): self
    {
        if ($thousandths < 0) {
            throw InvalidAmount::negativeUnitPrice((string) $thousandths);
        }

        return new self($thousandths);
    }

    public static function fromDecimal(Decimal|string $amount): self
    {
        $decimal = $amount instanceof Decimal ? $amount : Decimal::of($amount);

        if ($decimal->isNegative()) {
            throw InvalidAmount::negativeUnitPrice($decimal->value);
        }

        $thousandths = Rounding::halfUp(
            $decimal->mul(Decimal::of((string) self::UNITS_PER_WHOLE)),
            Rounding::MINOR_UNIT_SCALE
        );

        return new self((int) $thousandths);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Importe de la línea antes de descuentos, sin redondear. */
    public function times(Quantity $quantity): Decimal
    {
        return $this->toDecimal()->mul($quantity->toDecimal());
    }

    public function toDecimal(): Decimal
    {
        return Decimal::of((string) $this->thousandths)->div(Decimal::of((string) self::UNITS_PER_WHOLE));
    }

    /** Cadena con tres decimales para formularios: "33.333" */
    public function toDecimalString(): string
    {
        return Rounding::halfUp($this->toDecimal(), self::SCALE);
    }

    public function isZero(): bool
    {
        return $this->thousandths === 0;
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }
}
