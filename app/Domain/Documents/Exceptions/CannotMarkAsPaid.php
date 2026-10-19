<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

/** El marcador de cobro solo va en facturas emitidas y con una fecha que ya ha llegado. */
final class CannotMarkAsPaid extends DomainException
{
    public static function draft(): self
    {
        return new self('Un borrador no se puede marcar como cobrado: primero hay que emitirlo.');
    }

    public static function dateInFuture(CarbonImmutable $paidOn): self
    {
        return new self(sprintf('La fecha de cobro (%s) no puede ser posterior a hoy.', $paidOn->format('d/m/Y')));
    }
}
