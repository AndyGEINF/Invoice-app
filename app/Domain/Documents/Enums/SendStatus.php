<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Resultado de cada intento de envío por email.
 *
 * Un envío fallido se puede reintentar y no cambia el estado del documento.
 */
enum SendStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
        };
    }

    public function canRetry(): bool
    {
        return $this === self::Failed;
    }
}
