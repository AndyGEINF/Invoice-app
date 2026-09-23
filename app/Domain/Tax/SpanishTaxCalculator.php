<?php

declare(strict_types=1);

namespace App\Domain\Tax;

use App\Domain\Documents\Enums\TaxType;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Tax\Exceptions\InconsistentSurchargeRates;
use App\Domain\Tax\Policies\EquivalenceSurcharge;
use App\Domain\Tax\Policies\ExemptOperation;
use App\Domain\Tax\Policies\GeneralRegime;
use App\Domain\Tax\Policies\TaxPolicy;

/**
 * Motor de impuestos español (territorio común).
 *
 * 1. Base de cada línea sin redondear: cantidad × precio × (1 − dto.) × (1 − dto. global).
 * 2. Las políticas ajustan las líneas (recargo, régimen exento) y las validan.
 * 3. Se agrupan por (IVA, recargo, exención) y se redondea la base de cada grupo.
 * 4. La cuota de cada grupo se calcula sobre su base ya redondeada.
 * 5. La retención de IRPF va sobre la suma redondeada de las líneas con IRPF.
 * 6. Los totales salen de los grupos, nunca de las líneas.
 *
 * Redondear línea a línea produce descuadres de uno o dos céntimos que hacen
 * que la AEAT rechace el registro (decisión D3).
 */
final class SpanishTaxCalculator implements TaxCalculator
{
    /** Separador de la clave de agrupación. */
    private const string KEY_SEPARATOR = '|';

    /** @var list<TaxPolicy> */
    private readonly array $policies;

    /** @param list<TaxPolicy>|null $policies Por orden: las que ajustan tipos primero, las que validan al final. */
    public function __construct(?array $policies = null)
    {
        $this->policies = $policies ?? [
            new ExemptOperation,
            new EquivalenceSurcharge,
            new GeneralRegime,
        ];
    }

    public function calculate(iterable $lines, TaxContext $context): TaxBreakdown
    {
        $currency = $context->currency;
        $globalDiscount = $context->globalDiscount();

        /** @var array<string, array{line: LineInput, sum: Decimal}> $groups */
        $groups = [];
        $irpfSum = Decimal::zero();
        $lineBases = [];
        $surchargeByVat = [];
        $position = 0;

        foreach ($lines as $line) {
            $position++;
            $line = $this->applyPolicies($line, $context, $position);

            $base = $this->unroundedBase($line, $globalDiscount);
            $lineBases[] = Money::fromDecimal($base, $currency);

            $this->assertSingleSurchargePerVat($surchargeByVat, $line);

            $key = $this->groupKey($line);
            $groups[$key] ??= ['line' => $line, 'sum' => Decimal::zero()];
            $groups[$key]['sum'] = $groups[$key]['sum']->add($base);

            if ($line->irpfApplies) {
                $irpfSum = $irpfSum->add($base);
            }
        }

        return $this->buildBreakdown($groups, $irpfSum, $lineBases, $context);
    }

    private function applyPolicies(LineInput $line, TaxContext $context, int $position): LineInput
    {
        foreach ($this->policies as $policy) {
            if ($policy->applies($context)) {
                $line = $policy->apply($line, $context, $position);
            }
        }

        return $line;
    }

    /** cantidad × precio × (1 − dto. línea) × (1 − dto. global), sin redondear. */
    private function unroundedBase(LineInput $line, Percentage $globalDiscount): Decimal
    {
        $gross = $line->unitPrice->times($line->quantity);

        return $globalDiscount->applyDiscountTo($line->discount->applyDiscountTo($gross));
    }

    private function groupKey(LineInput $line): string
    {
        return implode(self::KEY_SEPARATOR, [
            $line->vatRate->value,
            $line->surchargeRate->value,
            $line->exemptionCode->value ?? '',
        ]);
    }

    /** @param array<string, string> $surchargeByVat */
    private function assertSingleSurchargePerVat(array &$surchargeByVat, LineInput $line): void
    {
        $vatKey = $line->vatRate->value.self::KEY_SEPARATOR.($line->exemptionCode->value ?? '');
        $known = $surchargeByVat[$vatKey] ?? null;

        if ($known !== null && $known !== $line->surchargeRate->value) {
            throw InconsistentSurchargeRates::forVatRate($line->vatRate->value);
        }

        $surchargeByVat[$vatKey] = $line->surchargeRate->value;
    }

    /**
     * @param  array<string, array{line: LineInput, sum: Decimal}>  $groups
     * @param  list<Money>  $lineBases
     */
    private function buildBreakdown(array $groups, Decimal $irpfSum, array $lineBases, TaxContext $context): TaxBreakdown
    {
        $currency = $context->currency;
        $taxableBase = Money::zero($currency);
        $vatTotal = Money::zero($currency);
        $surchargeTotal = Money::zero($currency);
        $taxGroups = [];

        foreach ($this->sortGroups($groups) as ['line' => $line, 'sum' => $sum]) {
            $base = Money::fromDecimal($sum, $currency);
            $vat = $base->multiplyBy($line->vatRate);

            $taxGroups[] = new TaxGroup(TaxType::Vat, $line->vatRate, $base, $vat, $line->exemptionCode);
            $taxableBase = $taxableBase->plus($base);
            $vatTotal = $vatTotal->plus($vat);

            if (! $line->surchargeRate->isZero()) {
                $surcharge = $base->multiplyBy($line->surchargeRate);
                $taxGroups[] = new TaxGroup(TaxType::Surcharge, $line->surchargeRate, $base, $surcharge);
                $surchargeTotal = $surchargeTotal->plus($surcharge);
            }
        }

        $irpfGroup = $this->irpfGroup($irpfSum, $context);
        $irpfTotal = $irpfGroup->amount ?? Money::zero($currency);

        return new TaxBreakdown(
            groups: $taxGroups,
            irpf: $irpfGroup,
            taxableBase: $taxableBase,
            vatTotal: $vatTotal,
            surchargeTotal: $surchargeTotal,
            irpfTotal: $irpfTotal,
            total: $taxableBase->plus($vatTotal)->plus($surchargeTotal)->minus($irpfTotal),
            lineBases: $lineBases,
        );
    }

    private function irpfGroup(Decimal $irpfSum, TaxContext $context): ?TaxGroup
    {
        if (! $context->appliesIrpf()) {
            return null;
        }

        $base = Money::fromDecimal($irpfSum, $context->currency);

        if ($base->isZero()) {
            return null;
        }

        return new TaxGroup(TaxType::Irpf, $context->irpfRate(), $base, $base->multiplyBy($context->irpfRate()));
    }

    /**
     * Orden estable del desglose: IVA de mayor a menor, y a igual IVA, por recargo
     * y causa de exención.
     *
     * @param  array<string, array{line: LineInput, sum: Decimal}>  $groups
     * @return list<array{line: LineInput, sum: Decimal}>
     */
    private function sortGroups(array $groups): array
    {
        $sorted = array_values($groups);

        usort($sorted, static function (array $a, array $b): int {
            $lineA = $a['line'];
            $lineB = $b['line'];

            $byVat = $lineB->vatRate->toDecimal()->compare($lineA->vatRate->toDecimal());

            if ($byVat !== 0) {
                return $byVat;
            }

            $bySurcharge = $lineB->surchargeRate->toDecimal()->compare($lineA->surchargeRate->toDecimal());

            if ($bySurcharge !== 0) {
                return $bySurcharge;
            }

            return strcmp($lineA->exemptionCode->value ?? '', $lineB->exemptionCode->value ?? '');
        });

        return $sorted;
    }
}
