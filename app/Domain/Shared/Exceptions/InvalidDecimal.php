<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use InvalidArgumentException;

final class InvalidDecimal extends InvalidArgumentException
{
    public static function notANumber(string $value): self
    {
        return new self(sprintf(
            'El valor "%s" no es un decimal exacto. Usa una cadena como "33.333", con punto y sin separador de miles.',
            $value
        ));
    }

    public static function negativeScale(int $scale): self
    {
        return new self(sprintf('La escala debe ser cero o positiva, recibida %d.', $scale));
    }
}
