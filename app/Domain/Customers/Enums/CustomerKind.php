<?php

declare(strict_types=1);

namespace App\Domain\Customers\Enums;

/**
 * Tipo de cliente.
 *
 * Una empresa siempre tiene identificador fiscal. A un particular no se le
 * practica retención de IRPF aunque el emisor la tenga por defecto.
 */
enum CustomerKind: string
{
    case Individual = 'individual';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particular',
            self::Business => 'Empresa o profesional',
        };
    }

    public function requiresTaxId(): bool
    {
        return $this === self::Business;
    }
}
