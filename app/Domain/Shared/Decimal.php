<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\InvalidDecimal;
use Stringable;

/**
 * Número decimal exacto de escala fija, respaldado por bcmath.
 *
 * Es la única aritmética permitida en el dominio: `float` está prohibido porque
 * 10,005 no es representable en binario y una factura no admite descuadres
 * (constitución, principio I).
 *
 * Todas las operaciones truncan a SCALE decimales; el redondeo fiscal a
 * céntimos es explícito y vive en {@see Rounding::halfUp()}.
 */
final readonly class Decimal implements Stringable
{
    /** Escala interna: suficiente para precios en milésimas por cantidades con 4 decimales. */
    public const int SCALE = 7;

    private function __construct(public string $value) {}

    public static function of(string|int|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $raw = trim((string) $value);

        if (preg_match('/^[+-]?\d+(\.\d+)?$/', $raw) !== 1) {
            throw InvalidDecimal::notANumber($raw);
        }

        return new self(self::normalize($raw));
    }

    public static function zero(): self
    {
        return new self(self::normalize('0'));
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->value, $other->value, self::SCALE));
    }

    public function sub(self $other): self
    {
        return new self(bcsub($this->value, $other->value, self::SCALE));
    }

    public function mul(self $other): self
    {
        return new self(bcmul($this->value, $other->value, self::SCALE));
    }

    /**
     * División truncada a SCALE decimales.
     *
     * @throws \DivisionByZeroError
     */
    public function div(self $other): self
    {
        return new self(bcdiv($this->value, $other->value, self::SCALE));
    }

    /** -1 si este valor es menor, 0 si son iguales, 1 si es mayor. */
    public function compare(self $other): int
    {
        return bccomp($this->value, $other->value, self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isZero(): bool
    {
        return $this->compare(self::zero()) === 0;
    }

    public function isNegative(): bool
    {
        return $this->compare(self::zero()) < 0;
    }

    public function isPositive(): bool
    {
        return $this->compare(self::zero()) > 0;
    }

    public function negate(): self
    {
        return self::zero()->sub($this);
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    /** Redondeo fiscal a `$scale` decimales, mitad hacia arriba. */
    public function round(int $scale = 2): string
    {
        return Rounding::halfUp($this, $scale);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** Fija la escala y evita el cero negativo. */
    private static function normalize(string $raw): string
    {
        $normalized = bcadd(ltrim($raw, '+'), '0', self::SCALE);

        return Rounding::stripNegativeZero($normalized);
    }
}
