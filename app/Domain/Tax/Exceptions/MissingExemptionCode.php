<?php

declare(strict_types=1);

namespace App\Domain\Tax\Exceptions;

use DomainException;

/**
 * Una línea sin IVA necesita la causa de exención: es lo que se imprime en la
 * factura y lo que espera la AEAT.
 */
final class MissingExemptionCode extends DomainException
{
    public static function forLine(int $position): self
    {
        return new self(sprintf(
            'La línea %d no lleva IVA: indica la causa de exención o de no sujeción.',
            $position
        ));
    }
}
