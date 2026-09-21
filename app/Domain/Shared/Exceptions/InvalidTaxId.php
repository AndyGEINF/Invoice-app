<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use InvalidArgumentException;

final class InvalidTaxId extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self(sprintf(
            'El identificador fiscal "%s" no es válido. Revisa el NIF, NIE, CIF o número de IVA intracomunitario.',
            $value
        ));
    }
}
