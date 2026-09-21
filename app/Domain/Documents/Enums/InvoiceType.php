<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Clasificación fiscal de la factura, con los códigos de la AEAT.
 *
 * Se guarda desde la primera migración aunque VeriFactu aún no esté activo:
 * renombrarlo después obligaría a tocar facturas ya emitidas.
 */
enum InvoiceType: string
{
    /** Factura completa, con datos del destinatario. */
    case F1 = 'F1';

    /** Factura simplificada: sin identificador fiscal del cliente. */
    case F2 = 'F2';

    /** Rectificativa por error fundado en derecho. */
    case R1 = 'R1';

    /** Rectificativa, resto de casos. */
    case R4 = 'R4';

    /** Rectificativa sobre una factura simplificada. */
    case R5 = 'R5';

    public function label(): string
    {
        return match ($this) {
            self::F1 => 'Factura completa',
            self::F2 => 'Factura simplificada',
            self::R1 => 'Rectificativa por error fundado en derecho',
            self::R4 => 'Rectificativa',
            self::R5 => 'Rectificativa de simplificada',
        };
    }

    public function isRectification(): bool
    {
        return in_array($this, [self::R1, self::R4, self::R5], true);
    }

    public function isSimplified(): bool
    {
        return $this === self::F2;
    }

    /** @return list<self> */
    public static function rectifications(): array
    {
        return [self::R1, self::R4, self::R5];
    }
}
