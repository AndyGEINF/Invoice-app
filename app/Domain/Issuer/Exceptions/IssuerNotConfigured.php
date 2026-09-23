<?php

declare(strict_types=1);

namespace App\Domain\Issuer\Exceptions;

use DomainException;

/**
 * Se lanza al intentar emitir sin haber completado los datos del emisor.
 */
final class IssuerNotConfigured extends DomainException
{
    /** @param list<string> $missing */
    private function __construct(string $message, public readonly array $missing)
    {
        parent::__construct($message);
    }

    /** @param list<string> $missing Campos que faltan, con las claves de {@see \App\Domain\Issuer\Issuer::missing()}. */
    public static function missing(array $missing): self
    {
        return new self(
            'Completa los datos del emisor antes de emitir. Falta: '.implode(', ', $missing).'.',
            $missing,
        );
    }
}
