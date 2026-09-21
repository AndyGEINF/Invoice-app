<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Forma de rectificar una factura, según la AEAT.
 */
enum RectificationType: string
{
    /** Por sustitución: la rectificativa reemplaza a la original. */
    case Substitution = 'S';

    /** Por diferencias: solo recoge la corrección. */
    case Differences = 'I';

    public function label(): string
    {
        return match ($this) {
            self::Substitution => 'Por sustitución',
            self::Differences => 'Por diferencias',
        };
    }

    /** Código de factura que corresponde a esta forma de rectificar. */
    public function invoiceType(): InvoiceType
    {
        return match ($this) {
            self::Substitution => InvoiceType::R1,
            self::Differences => InvoiceType::R4,
        };
    }
}
