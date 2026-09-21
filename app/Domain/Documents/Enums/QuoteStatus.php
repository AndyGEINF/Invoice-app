<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Estado de un presupuesto.
 *
 * Es un documento comercial: se edita y se borra libremente hasta que se
 * convierte en factura. La caducidad no es un estado, se deriva de la fecha de
 * validez.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviado',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Converted => 'Convertido',
        };
    }

    /** Un presupuesto convertido ya solo se consulta. */
    public function isEditable(): bool
    {
        return $this !== self::Converted;
    }

    public function allows(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Accepted, self::Rejected],
            self::Accepted => [self::Converted],
            self::Rejected, self::Converted => [],
        };
    }
}
