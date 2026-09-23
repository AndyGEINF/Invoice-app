<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

final class SeriesInactive extends DomainException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('La serie %s está desactivada: elige otra serie para emitir.', $code));
    }
}
