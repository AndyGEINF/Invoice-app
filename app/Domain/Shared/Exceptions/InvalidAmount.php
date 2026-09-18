<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use InvalidArgumentException;

final class InvalidAmount extends InvalidArgumentException
{
    public static function negativeUnitPrice(string $value): self
    {
        return new self(sprintf('El precio unitario no puede ser negativo: %s.', $value));
    }

    public static function negativeQuantity(string $value): self
    {
        return new self(sprintf('La cantidad no puede ser negativa: %s.', $value));
    }

    public static function percentageOutOfRange(string $value): self
    {
        return new self(sprintf('Un porcentaje debe estar entre 0 y 100, recibido %s.', $value));
    }
}
