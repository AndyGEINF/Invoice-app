<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Tipo de identificador fiscal.
 *
 * NIF, NIE y CIF son españoles; VAT_EU es el identificador a efectos de IVA de
 * otro país de la Unión Europea (se valida contra VIES); OTHER cubre clientes
 * de fuera de la UE, donde no hay formato que comprobar.
 */
enum TaxIdType: string
{
    case NIF = 'NIF';
    case NIE = 'NIE';
    case CIF = 'CIF';
    case VAT_EU = 'VAT_EU';
    case OTHER = 'OTHER';

    public function isSpanish(): bool
    {
        return in_array($this, [self::NIF, self::NIE, self::CIF], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::NIF => 'NIF',
            self::NIE => 'NIE',
            self::CIF => 'CIF',
            self::VAT_EU => 'IVA intracomunitario',
            self::OTHER => 'Otro',
        };
    }
}
