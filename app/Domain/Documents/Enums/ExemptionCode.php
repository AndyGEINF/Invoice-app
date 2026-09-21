<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Causa de exención o no sujeción al IVA.
 *
 * Una línea sin IVA obliga a indicar el motivo: es lo que se imprime en la
 * factura y lo que la AEAT espera en el registro.
 */
enum ExemptionCode: string
{
    case E1 = 'E1';
    case E2 = 'E2';
    case E3 = 'E3';
    case E4 = 'E4';
    case E5 = 'E5';
    case E6 = 'E6';

    /** Operación no sujeta: fuera del ámbito del impuesto. */
    case NotSubject = 'NS';

    /** Texto legal que se imprime en el PDF. */
    public function legalText(): string
    {
        return match ($this) {
            self::E1 => 'Operación exenta por el artículo 20 de la Ley 37/1992 del IVA',
            self::E2 => 'Operación exenta por el artículo 21 de la Ley 37/1992 del IVA',
            self::E3 => 'Operación exenta por el artículo 22 de la Ley 37/1992 del IVA',
            self::E4 => 'Operación exenta por los artículos 23 y 24 de la Ley 37/1992 del IVA',
            self::E5 => 'Operación exenta por el artículo 25 de la Ley 37/1992 del IVA',
            self::E6 => 'Operación exenta por otros motivos',
            self::NotSubject => 'Operación no sujeta al IVA',
        };
    }

    public function label(): string
    {
        return $this->value.' · '.$this->legalText();
    }
}
