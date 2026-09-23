<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/**
 * La serie ya numera un año posterior: emitir con fecha del año anterior
 * rompería la correlación entre número y fecha.
 */
final class SeriesYearClosed extends DomainException
{
    public static function for(string $code, int $issueYear, int $currentYear): self
    {
        return new self(sprintf(
            'La serie %s ya está numerando el año %d: no se puede emitir con fecha de %d.',
            $code,
            $currentYear,
            $issueYear
        ));
    }
}
