<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/**
 * No se emite una factura vacía.
 */
final class DocumentHasNoLines extends DomainException
{
    public static function cannotIssue(): self
    {
        return new self('Añade al menos una línea antes de emitir.');
    }
}
