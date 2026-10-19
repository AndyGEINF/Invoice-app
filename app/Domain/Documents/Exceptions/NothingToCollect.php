<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/** Una factura de importe cero ya cuenta como cobrada: no hay nada que marcar. */
final class NothingToCollect extends DomainException
{
    public static function for(string $document): self
    {
        return new self(sprintf('La factura %s tiene un total de 0,00 €: no hay nada que cobrar.', $document));
    }
}
