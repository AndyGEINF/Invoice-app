<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use LogicException;

final class BillingRecordIsAppendOnly extends LogicException
{
    public static function forOperation(string $operation): self
    {
        return new self(sprintf(
            'Los registros de facturación solo se añaden: no se permite %s. Cualquier cambio rompería la cadena de huellas.',
            $operation
        ));
    }
}
