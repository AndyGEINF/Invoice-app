<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Estado de una factura o rectificativa.
 *
 * No existe el camino de vuelta a borrador ni el borrado desde emitida:
 * corregir es emitir una rectificativa (decisión D6).
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Sent = 'sent';
    case Rectified = 'rectified';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Emitida',
            self::Sent => 'Enviada',
            self::Rectified => 'Rectificada',
        };
    }

    /** Solo el borrador se puede editar o borrar. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isIssued(): bool
    {
        return $this !== self::Draft;
    }

    /** Transiciones permitidas desde este estado. */
    public function allows(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued],
            self::Issued => [self::Sent, self::Rectified],
            self::Sent => [self::Rectified],
            self::Rectified => [],
        };
    }
}
