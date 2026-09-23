<?php

declare(strict_types=1);

namespace App\Domain\Tax\Policies;

use App\Domain\Shared\Percentage;
use App\Domain\Tax\LineInput;
use App\Domain\Tax\TaxContext;

/**
 * Recargo de equivalencia: solo se repercute a clientes acogidos a ese
 * régimen. Para el resto se anula, aunque la línea traiga un tipo.
 */
final class EquivalenceSurcharge implements TaxPolicy
{
    public function applies(TaxContext $context): bool
    {
        return ! $context->customerSurchargeApplies;
    }

    public function apply(LineInput $line, TaxContext $context, int $position): LineInput
    {
        return $line->withSurchargeRate(Percentage::zero());
    }
}
