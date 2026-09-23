<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;

/**
 * Línea tal como la necesita el motor de impuestos.
 *
 * La cantidad solo puede ser negativa en una rectificativa; el motor lo
 * comprueba con {@see TaxContext::$allowNegativeQuantities}.
 */
final readonly class LineInput
{
    public function __construct(
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Percentage $discount,
        public Percentage $vatRate,
        public Percentage $surchargeRate,
        public bool $irpfApplies = false,
        public ?ExemptionCode $exemptionCode = null,
    ) {}

    /** Atajo para construir líneas a partir de cadenas decimales. */
    public static function of(
        string $quantity,
        string $unitPrice,
        string $vatRate,
        string $discount = '0',
        string $surchargeRate = '0',
        bool $irpfApplies = false,
        ?ExemptionCode $exemptionCode = null,
    ): self {
        return new self(
            Quantity::signed($quantity),
            UnitPrice::fromDecimal($unitPrice),
            Percentage::of($discount),
            Percentage::of($vatRate),
            Percentage::of($surchargeRate),
            $irpfApplies,
            $exemptionCode,
        );
    }

    public function withVatRate(Percentage $vatRate): self
    {
        return new self(
            $this->quantity,
            $this->unitPrice,
            $this->discount,
            $vatRate,
            $this->surchargeRate,
            $this->irpfApplies,
            $this->exemptionCode,
        );
    }

    public function withSurchargeRate(Percentage $surchargeRate): self
    {
        return new self(
            $this->quantity,
            $this->unitPrice,
            $this->discount,
            $this->vatRate,
            $surchargeRate,
            $this->irpfApplies,
            $this->exemptionCode,
        );
    }
}
