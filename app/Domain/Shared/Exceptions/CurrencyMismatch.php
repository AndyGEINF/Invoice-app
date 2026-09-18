<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use App\Domain\Shared\Currency;
use DomainException;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $a, Currency $b): self
    {
        return new self(sprintf(
            'No se pueden operar importes en monedas distintas: %s y %s.',
            $a->value,
            $b->value
        ));
    }
}
