<?php

declare(strict_types=1);

namespace App\Domain\Tax\Exceptions;

use DomainException;

/**
 * Solo las rectificativas admiten cantidades negativas (decisión del
 * 2026-09-23): una factura o un presupuesto con importes negativos se corrige
 * emitiendo una rectificativa.
 */
final class NegativeQuantityNotAllowed extends DomainException
{
    public static function forLine(int $position): self
    {
        return new self(sprintf(
            'La línea %d tiene una cantidad negativa. Solo las rectificativas admiten cantidades negativas.',
            $position
        ));
    }
}
