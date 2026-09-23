<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Documents\Enums\TaxType;
use App\Domain\Shared\Money;

/**
 * Resultado del cálculo: grupos del desglose, retención y totales.
 *
 * Los totales salen siempre de los grupos ya redondeados, nunca de la suma de
 * las líneas: por eso el total cuadra al céntimo con el desglose (decisión D3).
 */
final readonly class TaxBreakdown
{
    /**
     * @param  list<TaxGroup>  $groups  Grupos de IVA y de recargo de equivalencia.
     * @param  list<Money>  $lineBases  Base de cada línea redondeada, solo informativa.
     */
    public function __construct(
        public array $groups,
        public ?TaxGroup $irpf,
        public Money $taxableBase,
        public Money $vatTotal,
        public Money $surchargeTotal,
        public Money $irpfTotal,
        public Money $total,
        public array $lineBases,
    ) {}

    /** @return list<TaxGroup> */
    public function vatGroups(): array
    {
        return $this->groupsOf(TaxType::Vat);
    }

    /** @return list<TaxGroup> */
    public function surchargeGroups(): array
    {
        return $this->groupsOf(TaxType::Surcharge);
    }

    /**
     * Todos los grupos que se guardan en `document_taxes`, incluida la retención.
     *
     * @return list<TaxGroup>
     */
    public function allGroups(): array
    {
        return $this->irpf === null ? $this->groups : [...$this->groups, $this->irpf];
    }

    /** @return list<TaxGroup> */
    private function groupsOf(TaxType $type): array
    {
        return array_values(array_filter(
            $this->groups,
            static fn (TaxGroup $group): bool => $group->type === $type,
        ));
    }
}
