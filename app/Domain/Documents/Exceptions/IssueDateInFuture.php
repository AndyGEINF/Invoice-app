<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * Una factura no se puede expedir con fecha futura.
 */
final class IssueDateInFuture extends DomainException
{
    public const string DATE_FORMAT = 'd/m/Y';

    public static function for(CarbonImmutable $issueDate, CarbonImmutable $today): self
    {
        return new self(sprintf(
            'La fecha de expedición (%s) no puede ser posterior a hoy (%s).',
            $issueDate->format(self::DATE_FORMAT),
            $today->format(self::DATE_FORMAT)
        ));
    }
}
