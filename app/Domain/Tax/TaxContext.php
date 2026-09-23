<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Shared\Currency;
use App\Domain\Shared\Enums\VatRegime;
use App\Domain\Shared\Percentage;

/**
 * Datos del documento que condicionan el cálculo: régimen del emisor, si al
 * cliente se le aplica recargo o retención, y el descuento global.
 */
final readonly class TaxContext
{
    public function __construct(
        public Currency $currency = Currency::EUR,
        public ?Percentage $globalDiscount = null,
        public ?Percentage $irpfRate = null,
        public VatRegime $issuerRegime = VatRegime::General,
        public bool $customerSurchargeApplies = false,
        public bool $customerIrpfApplies = false,
        /** Solo una rectificativa admite cantidades negativas. */
        public bool $allowNegativeQuantities = false,
    ) {}

    public function globalDiscount(): Percentage
    {
        return $this->globalDiscount ?? Percentage::zero();
    }

    public function irpfRate(): Percentage
    {
        return $this->irpfRate ?? Percentage::zero();
    }

    /** La retención solo se practica si el cliente la admite y hay un tipo mayor que cero. */
    public function appliesIrpf(): bool
    {
        return $this->customerIrpfApplies && ! $this->irpfRate()->isZero();
    }
}
