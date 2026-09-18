<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\CurrencyMismatch;
use NumberFormatter;
use Stringable;

/**
 * Importe monetario en unidades menores enteras (céntimos).
 *
 * Nunca se guarda ni se opera en `float`: los totales de una factura se suman
 * en enteros y el redondeo a céntimos es siempre explícito y HALF_UP
 * (constitución, principio I).
 */
final readonly class Money implements Stringable
{
    /** Escala de trabajo cuando el importe ya está en unidades menores. */
    private const int MINOR_UNIT_SCALE = Rounding::MINOR_UNIT_SCALE;

    private function __construct(
        public int $cents,
        public Currency $currency,
    ) {}

    public static function fromCents(int $cents, Currency|string $currency = Currency::EUR): self
    {
        return new self($cents, self::resolveCurrency($currency));
    }

    /** Redondea a céntimos con la mitad hacia arriba. */
    public static function fromDecimal(Decimal|string $amount, Currency|string $currency = Currency::EUR): self
    {
        $resolved = self::resolveCurrency($currency);
        $decimal = $amount instanceof Decimal ? $amount : Decimal::of($amount);

        $minorUnits = $decimal->mul(Decimal::of((string) $resolved->minorUnits()));

        return new self((int) Rounding::halfUp($minorUnits, self::MINOR_UNIT_SCALE), $resolved);
    }

    public static function zero(Currency|string $currency = Currency::EUR): self
    {
        return new self(0, self::resolveCurrency($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    /** Aplica un porcentaje (IVA, recargo, IRPF, descuento) redondeando a céntimos. */
    public function multiplyBy(Percentage $percentage): self
    {
        return new self(
            (int) Rounding::halfUp($percentage->applyTo($this->toMinorUnitsDecimal()), self::MINOR_UNIT_SCALE),
            $this->currency
        );
    }

    /** Multiplica por una cantidad redondeando a céntimos. */
    public function multiplyByQuantity(Quantity $quantity): self
    {
        return new self(
            (int) Rounding::halfUp($this->toMinorUnitsDecimal()->mul($quantity->toDecimal()), self::MINOR_UNIT_SCALE),
            $this->currency
        );
    }

    public function negate(): self
    {
        return new self(-$this->cents, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->cents === $other->cents;
    }

    public function gte(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents >= $other->cents;
    }

    public function gt(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function lt(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents < $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /** Importe en unidades de moneda: 19250 céntimos → 192.5000000 */
    public function toDecimal(): Decimal
    {
        return Decimal::of((string) $this->cents)->div(Decimal::of((string) $this->currency->minorUnits()));
    }

    /** Cadena con los decimales de la moneda, apta para guardar o comparar: "192.50" */
    public function toDecimalString(): string
    {
        return Rounding::halfUp($this->toDecimal(), $this->currency->decimals());
    }

    /** Formato humano: "192,50 €" (con espacio duro antes del símbolo). */
    public function format(string $locale = 'es-ES'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency(
            (float) $this->toDecimalString(),
            $this->currency->value
        );
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    private function toMinorUnitsDecimal(): Decimal
    {
        return Decimal::of((string) $this->cents);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }

    private static function resolveCurrency(Currency|string $currency): Currency
    {
        return $currency instanceof Currency ? $currency : Currency::from($currency);
    }
}
