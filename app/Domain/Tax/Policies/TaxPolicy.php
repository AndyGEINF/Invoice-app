<?php

declare(strict_types=1);

namespace App\Domain\Tax\Policies;

use App\Domain\Tax\LineInput;
use App\Domain\Tax\TaxContext;

/**
 * Regla fiscal que ajusta una línea antes de calcular.
 *
 * Cada régimen o caso especial es una política aparte, de modo que añadir uno
 * nuevo (inversión del sujeto pasivo, intracomunitarias) no toca el motor.
 */
interface TaxPolicy
{
    public function applies(TaxContext $context): bool;

    /** @param int $position Posición de la línea, para los mensajes de error. */
    public function apply(LineInput $line, TaxContext $context, int $position): LineInput;
}
