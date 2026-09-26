<?php

declare(strict_types=1);

namespace App\Domain\Documents\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * En una serie, número y fecha avanzan juntos: una factura no puede llevar una
 * fecha anterior a la última ya emitida en su serie.
 */
final class IssueDateBeforeSeriesLast extends DomainException
{
    public static function for(string $seriesCode, CarbonImmutable $issueDate, CarbonImmutable $lastIssueDate): self
    {
        return new self(sprintf(
            'La serie %s ya tiene una factura del %s: la nueva no puede llevar fecha del %s.',
            $seriesCode,
            $lastIssueDate->format(IssueDateInFuture::DATE_FORMAT),
            $issueDate->format(IssueDateInFuture::DATE_FORMAT)
        ));
    }
}
