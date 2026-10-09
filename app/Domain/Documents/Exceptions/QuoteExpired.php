<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * Un presupuesto caducado no se acepta: sus precios ya no están garantizados.
 * Se duplica y se envía de nuevo con otra fecha de validez.
 */
final class QuoteExpired extends DomainException
{
    public static function cannotAccept(string $quote, CarbonImmutable $validUntil): self
    {
        return new self(sprintf(
            'El presupuesto %s caducó el %s y ya no se puede aceptar. Duplícalo para enviar uno nuevo.',
            $quote,
            $validUntil->format('d/m/Y')
        ));
    }
}
