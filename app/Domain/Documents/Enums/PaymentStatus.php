<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Estado de cobro de una factura.
 *
 * Es un valor derivado del marcador `paid_at`, de la fecha de vencimiento y del
 * total: nunca se guarda en la base de datos. La aplicación no gestiona pagos
 * (constitución, principio VII).
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Pendiente',
            self::Paid => 'Cobrada',
            self::Overdue => 'Vencida',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
