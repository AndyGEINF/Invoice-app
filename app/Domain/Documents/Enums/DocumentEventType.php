<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Acciones que quedan registradas en el historial de un documento.
 */
enum DocumentEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Issued = 'issued';
    case Sent = 'sent';
    case SendFailed = 'send_failed';
    case MarkedPaid = 'marked_paid';
    case UnmarkedPaid = 'unmarked_paid';
    case Rectified = 'rectified';
    case Converted = 'converted';
    case QuoteSent = 'quote_sent';
    case QuoteAccepted = 'quote_accepted';
    case QuoteRejected = 'quote_rejected';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creado',
            self::Updated => 'Modificado',
            self::Deleted => 'Borrado',
            self::Issued => 'Emitida',
            self::Sent => 'Enviada por email',
            self::SendFailed => 'Fallo al enviar',
            self::MarkedPaid => 'Marcada como cobrada',
            self::UnmarkedPaid => 'Marca de cobro retirada',
            self::Rectified => 'Rectificada',
            self::Converted => 'Convertido en factura',
            self::QuoteSent => 'Presupuesto enviado',
            self::QuoteAccepted => 'Presupuesto aceptado',
            self::QuoteRejected => 'Presupuesto rechazado',
        };
    }
}
