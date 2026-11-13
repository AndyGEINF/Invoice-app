<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use DomainException;

/**
 * Cambios que una serie no admite (FR-032): con documentos emitidos su formato
 * queda fijado y el contador solo avanza, una serie con documentos no se borra
 * y cada tipo de documento necesita siempre una serie por defecto activa.
 */
final class SeriesLocked extends DomainException
{
    public static function formatFixed(string $code): self
    {
        return new self(sprintf(
            'La serie %s ya tiene documentos emitidos: su código, prefijo, dígitos y reinicio anual no se pueden cambiar. Si necesitas otro formato, crea una serie nueva.',
            $code,
        ));
    }

    public static function counterBackwards(string $code, int $next): self
    {
        return new self(sprintf(
            'La serie %s ya tiene documentos emitidos: el siguiente número no puede bajar de %d o se repetirían números.',
            $code,
            $next,
        ));
    }

    public static function typeFixed(string $code): self
    {
        return new self(sprintf('La serie %s ya tiene documentos: no se puede cambiar el tipo de documento.', $code));
    }

    public static function hasDocuments(string $code): self
    {
        return new self(sprintf('La serie %s tiene documentos y no se puede borrar. Puedes desactivarla para que no se use más.', $code));
    }

    public static function defaultRequired(string $code): self
    {
        return new self(sprintf(
            'La serie %s es la serie por defecto. Marca otra como serie por defecto antes de quitarla, desactivarla o borrarla.',
            $code,
        ));
    }

    public static function defaultMustBeActive(): self
    {
        return new self('Una serie desactivada no puede ser la serie por defecto.');
    }
}
