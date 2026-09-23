<?php

declare(strict_types=1);

namespace App\Domain\Tax\Exceptions;

use DomainException;

/**
 * Dentro de un documento, cada tipo de IVA lleva un único tipo de recargo de
 * equivalencia. Mezclar dos recargos para el mismo IVA daría dos grupos del
 * desglose con el mismo tipo, que la AEAT no admite.
 */
final class InconsistentSurchargeRates extends DomainException
{
    public static function forVatRate(string $vatRate): self
    {
        return new self(sprintf(
            'Hay líneas al %s %% de IVA con distintos recargos de equivalencia. Usa el mismo recargo para el mismo IVA.',
            $vatRate
        ));
    }
}
