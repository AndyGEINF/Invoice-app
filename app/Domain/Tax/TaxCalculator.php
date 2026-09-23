<?php

declare(strict_types=1);

namespace App\Domain\Tax;

/**
 * Calcula el desglose de impuestos y los totales de un documento.
 *
 * El algoritmo normativo y sus vectores de prueba están en
 * specs/001-nucleo-facturacion/contracts/tax-engine.md.
 */
interface TaxCalculator
{
    /** @param iterable<LineInput> $lines */
    public function calculate(iterable $lines, TaxContext $context): TaxBreakdown;
}
