<?php

declare(strict_types=1);

namespace App\Domain\Issuer\Exceptions;

use App\Domain\Issuer\Issuer;
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

    /** @param list<string> $missing Campos que faltan, con las claves de {@see Issuer::missing()}. */
    public static function missing(array $missing): self
    {
        $labels = array_map(static fn (string $key): string => Issuer::MISSING_LABELS[$key] ?? $key, $missing);

        return new self(
            'Completa los datos del emisor antes de emitir. Falta: '.implode(', ', $labels).'.',
            $missing,
        );
    }
}
