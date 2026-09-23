<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;

/**
 * Grupo del desglose: base y cuota de un tipo impositivo.
 *
 * Es lo que se guarda en `document_taxes` y lo que valida la AEAT.
 */
final readonly class TaxGroup
{
    public function __construct(
        public TaxType $type,
        public Percentage $rate,
        public Money $base,
        public Money $amount,
        public ?ExemptionCode $exemptionCode = null,
    ) {}
}
