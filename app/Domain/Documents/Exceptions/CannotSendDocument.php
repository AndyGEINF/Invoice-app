<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/** Solo se envían documentos emitidos y presupuestos ya enviados o posteriores. */
final class CannotSendDocument extends DomainException
{
    public static function draft(): self
    {
        return new self('Un borrador no se puede enviar por email: primero hay que emitirlo.');
    }
}
