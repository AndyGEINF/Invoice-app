<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Tipo de impuesto de cada grupo del desglose.
 *
 * El IVA y el recargo se suman al total; el IRPF se resta.
 */
enum TaxType: string
{
    case Vat = 'IVA';
    case Surcharge = 'RE';
    case Irpf = 'IRPF';

    public function label(): string
    {
        return match ($this) {
            self::Vat => 'IVA',
            self::Surcharge => 'Recargo de equivalencia',
            self::Irpf => 'Retención IRPF',
        };
    }

    /** El IRPF resta del total a pagar. */
    public function isWithholding(): bool
    {
        return $this === self::Irpf;
    }
}
