<?php

declare(strict_types=1);

namespace App\Domain\Tax\Policies;

use App\Domain\Shared\Percentage;
use App\Domain\Tax\LineInput;
use App\Domain\Tax\TaxContext;

/**
 * Emisor exento de IVA: ninguna línea repercute IVA, y cada una necesita su
 * causa de exención (la comprueba {@see GeneralRegime}).
 */
final class ExemptOperation implements TaxPolicy
{
    public function applies(TaxContext $context): bool
    {
        return $context->issuerRegime->isExempt();
    }

    public function apply(LineInput $line, TaxContext $context, int $position): LineInput
    {
        return $line->withVatRate(Percentage::zero())->withSurchargeRate(Percentage::zero());
    }
}
