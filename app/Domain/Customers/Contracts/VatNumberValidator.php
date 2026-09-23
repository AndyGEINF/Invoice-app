<?php

declare(strict_types=1);

namespace App\Domain\Customers\Contracts;

use App\Domain\Shared\TaxId;

/**
 * Comprueba un número de IVA intracomunitario contra VIES.
 *
 * Se llama desde un job de cola y nunca debe lanzar por un fallo del servicio:
 * en ese caso devuelve {@see ViesResult::unavailable()}.
 */
interface VatNumberValidator
{
    public function validate(TaxId $taxId): ViesResult;
}
