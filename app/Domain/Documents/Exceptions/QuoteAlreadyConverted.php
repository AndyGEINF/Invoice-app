<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/**
 * Un presupuesto se convierte en factura una sola vez: la segunda conversión
 * duplicaría lo facturado.
 */
final class QuoteAlreadyConverted extends DomainException
{
    public static function for(string $quote): self
    {
        return new self(sprintf(
            'El presupuesto %s ya se convirtió en factura. Si necesitas otra, duplica el presupuesto.',
            $quote
        ));
    }
}
