<?php

declare(strict_types=1);

namespace App\Domain\Tax\Policies;

use App\Domain\Tax\Exceptions\MissingExemptionCode;
use App\Domain\Tax\Exceptions\NegativeQuantityNotAllowed;
use App\Domain\Tax\LineInput;
use App\Domain\Tax\TaxContext;

/**
 * Comprobaciones que valen para cualquier régimen: una línea sin IVA lleva
 * causa de exención, y solo una rectificativa admite cantidades negativas.
 *
 * Se aplica la última, cuando las demás políticas ya han ajustado los tipos.
 */
final class GeneralRegime implements TaxPolicy
{
    public function applies(TaxContext $context): bool
    {
        return true;
    }

    public function apply(LineInput $line, TaxContext $context, int $position): LineInput
    {
        if ($line->quantity->isNegative() && ! $context->allowNegativeQuantities) {
            throw NegativeQuantityNotAllowed::forLine($position);
        }

        if ($line->vatRate->isZero() && $line->exemptionCode === null) {
            throw MissingExemptionCode::forLine($position);
        }

        return $line;
    }
}
