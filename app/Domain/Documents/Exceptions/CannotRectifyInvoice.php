<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/**
 * Solo se rectifica una factura emitida: un borrador se corrige editándolo, y
 * una rectificativa no puede apuntar a algo que no existe para Hacienda.
 */
final class CannotRectifyInvoice extends DomainException
{
    public static function draft(): self
    {
        return new self('Esta factura aún es un borrador: corrígela editándola, no hace falta una rectificativa.');
    }

    public static function originalNotIssued(): self
    {
        return new self('La factura que corrige esta rectificativa no está emitida.');
    }
}
