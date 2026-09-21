<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Régimen de IVA del emisor.
 */
enum VatRegime: string
{
    /** Régimen general: repercute IVA al tipo que corresponda. */
    case General = 'general';

    /** Recargo de equivalencia: comercio minorista. */
    case Surcharge = 'surcharge';

    /** Exento: no repercute IVA y cada línea lleva su causa de exención. */
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Régimen general',
            self::Surcharge => 'Recargo de equivalencia',
            self::Exempt => 'Exento de IVA',
        };
    }

    public function isExempt(): bool
    {
        return $this === self::Exempt;
    }
}
