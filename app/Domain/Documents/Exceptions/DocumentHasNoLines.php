<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/**
 * No se emite una factura vacía ni se envía un presupuesto vacío.
 */
final class DocumentHasNoLines extends DomainException
{
    public static function cannotIssue(): self
    {
        return new self('Añade al menos una línea antes de emitir.');
    }

    public static function cannotSend(): self
    {
        return new self('Añade al menos una línea antes de enviar el presupuesto.');
    }
}
