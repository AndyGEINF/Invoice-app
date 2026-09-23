<?php

declare(strict_types=1);

use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Shared\Enums\VatRegime;
use App\Domain\Shared\Exceptions\InvalidAmount;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use App\Domain\Tax\Exceptions\InconsistentSurchargeRates;
use App\Domain\Tax\Exceptions\MissingExemptionCode;
use App\Domain\Tax\Exceptions\NegativeQuantityNotAllowed;
use App\Domain\Tax\LineInput;
use App\Domain\Tax\SpanishTaxCalculator;
use App\Domain\Tax\TaxBreakdown;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxGroup;

/*
| Vectores de prueba de specs/001-nucleo-facturacion/contracts/tax-engine.md.
| Los importes esperados van en euros con dos decimales, como en el contrato.
*/

function calculate(array $lines, ?TaxContext $context = null): TaxBreakdown
{
    return (new SpanishTaxCalculator)->calculate($lines, $context ?? new TaxContext);
}

/** Resume un grupo como "IVA 21.00: 150.00 / 31.50". */
function describeGroup(TaxGroup $group): string
{
    return sprintf('%s %s: %s / %s', $group->type->value, $group->rate->value, $group->base, $group->amount);
}

/** @return list<string> */
function describeGroups(TaxBreakdown $breakdown): array
{
    return array_map(describeGroup(...), $breakdown->allGroups());
}

describe('vectores del contrato', function () {
    it('V1 · un tipo, sin descuentos', function () {
        $b = calculate([LineInput::of('2', '100', '21')]);

        expect(describeGroups($b))->toBe(['IVA 21.00: 200.00 / 42.00'])
            ->and((string) $b->total)->toBe('242.00');
    });

    it('V2 · tipos mixtos y precios con milésimas', function () {
        $b = calculate([
            LineInput::of('3', '33.333', '21'),
            LineInput::of('1', '50.005', '21'),
            LineInput::of('1', '10', '10'),
        ]);

        expect(describeGroups($b))->toBe(['IVA 21.00: 150.00 / 31.50', 'IVA 10.00: 10.00 / 1.00'])
            ->and((string) $b->taxableBase)->toBe('160.00')
            ->and((string) $b->vatTotal)->toBe('32.50')
            ->and((string) $b->total)->toBe('192.50')
            ->and(array_map('strval', $b->lineBases))->toBe(['100.00', '50.01', '10.00']);
    });

    it('V3 · redondea por grupo y no por línea', function () {
        $b = calculate([
            LineInput::of('1', '0.333', '21'),
            LineInput::of('1', '0.333', '21'),
            LineInput::of('1', '0.333', '21'),
        ]);

        // Por línea saldría 0,99 + 0,21 = 1,20: el descuadre que rechaza la AEAT.
        expect(describeGroups($b))->toBe(['IVA 21.00: 1.00 / 0.21'])
            ->and((string) $b->total)->toBe('1.21');
    });

    it('V4 · retención de IRPF', function () {
        $b = calculate(
            [LineInput::of('1', '1000', '21', irpfApplies: true)],
            new TaxContext(irpfRate: Percentage::of('15'), customerIrpfApplies: true),
        );

        expect(describeGroups($b))->toBe(['IVA 21.00: 1000.00 / 210.00', 'IRPF 15.00: 1000.00 / 150.00'])
            ->and((string) $b->irpfTotal)->toBe('150.00')
            ->and((string) $b->total)->toBe('1060.00');
    });

    it('V5 · recargo de equivalencia', function () {
        $b = calculate(
            [LineInput::of('1', '100', '21', surchargeRate: '5.20')],
            new TaxContext(customerSurchargeApplies: true),
        );

        expect(describeGroups($b))->toBe(['IVA 21.00: 100.00 / 21.00', 'RE 5.20: 100.00 / 5.20'])
            ->and((string) $b->surchargeTotal)->toBe('5.20')
            ->and((string) $b->total)->toBe('126.20');
    });

    it('V6 · operación exenta con su causa', function () {
        $b = calculate([LineInput::of('1', '500', '0', exemptionCode: ExemptionCode::E1)]);

        expect(describeGroups($b))->toBe(['IVA 0.00: 500.00 / 0.00'])
            ->and($b->vatGroups()[0]->exemptionCode)->toBe(ExemptionCode::E1)
            ->and((string) $b->total)->toBe('500.00');
    });

    it('V7 · descuento de línea', function () {
        $b = calculate([LineInput::of('4', '25', '21', discount: '10')]);

        expect(describeGroups($b))->toBe(['IVA 21.00: 90.00 / 18.90'])
            ->and((string) $b->total)->toBe('108.90');
    });

    it('V8 · descuento global', function () {
        $b = calculate(
            [LineInput::of('1', '200', '21')],
            new TaxContext(globalDiscount: Percentage::of('5')),
        );

        expect(describeGroups($b))->toBe(['IVA 21.00: 190.00 / 39.90'])
            ->and((string) $b->total)->toBe('229.90');
    });

    it('V9 · mitad hacia arriba exacta, que falla con float', function () {
        $b = calculate([LineInput::of('1', '10.005', '21')]);

        expect(describeGroups($b))->toBe(['IVA 21.00: 10.01 / 2.10'])
            ->and((string) $b->total)->toBe('12.11');
    });

    it('V10 · IRPF solo sobre las líneas que lo llevan', function () {
        $lines = [
            LineInput::of('1', '100', '21', irpfApplies: true),
            LineInput::of('1', '50', '21'),
        ];

        $conIrpf = calculate($lines, new TaxContext(irpfRate: Percentage::of('15'), customerIrpfApplies: true));
        $sinIrpf = calculate($lines, new TaxContext(irpfRate: Percentage::of('15'), customerIrpfApplies: false));

        expect(describeGroups($conIrpf))->toBe(['IVA 21.00: 150.00 / 31.50', 'IRPF 15.00: 100.00 / 15.00'])
            ->and((string) $conIrpf->total)->toBe('166.50')
            ->and($sinIrpf->irpf)->toBeNull()
            ->and((string) $sinIrpf->total)->toBe('181.50');
    });

    it('V11 · cantidad y precio cero', function () {
        $b = calculate([LineInput::of('0', '100', '21'), LineInput::of('1', '0', '21')]);

        expect(describeGroups($b))->toBe(['IVA 21.00: 0.00 / 0.00'])
            ->and($b->total->isZero())->toBeTrue();
    });
});

describe('V12 · entradas inválidas', function () {
    it('rechaza una cantidad negativa en una factura', function () {
        calculate([LineInput::of('-1', '100', '21')]);
    })->throws(NegativeQuantityNotAllowed::class);

    it('rechaza un precio negativo', function () {
        LineInput::of('1', '-0.001', '21');
    })->throws(InvalidAmount::class);

    it('exige la causa de exención en una línea sin IVA', function () {
        calculate([LineInput::of('1', '100', '0')]);
    })->throws(MissingExemptionCode::class);

    it('rechaza un descuento mayor que el 100 %', function () {
        LineInput::of('1', '100', '21', discount: '101');
    })->throws(InvalidAmount::class);

    it('rechaza dos recargos distintos para el mismo IVA', function () {
        calculate(
            [LineInput::of('1', '100', '21', surchargeRate: '5.20'), LineInput::of('1', '100', '21', surchargeRate: '1.40')],
            new TaxContext(customerSurchargeApplies: true),
        );
    })->throws(InconsistentSurchargeRates::class);
});

describe('reglas fiscales', function () {
    it('anula el recargo si el cliente no está en ese régimen', function () {
        $b = calculate([LineInput::of('1', '100', '21', surchargeRate: '5.20')]);

        expect(describeGroups($b))->toBe(['IVA 21.00: 100.00 / 21.00'])
            ->and($b->surchargeTotal->isZero())->toBeTrue();
    });

    it('un emisor exento no repercute IVA y exige la causa', function () {
        $exento = new TaxContext(issuerRegime: VatRegime::Exempt);

        $b = calculate([LineInput::of('1', '100', '21', exemptionCode: ExemptionCode::E1)], $exento);

        expect(describeGroups($b))->toBe(['IVA 0.00: 100.00 / 0.00'])
            ->and((string) $b->total)->toBe('100.00');

        expect(fn () => calculate([LineInput::of('1', '100', '21')], $exento))
            ->toThrow(MissingExemptionCode::class);
    });

    it('no crea grupo de IRPF si ninguna línea lo lleva', function () {
        $b = calculate(
            [LineInput::of('1', '100', '21')],
            new TaxContext(irpfRate: Percentage::of('15'), customerIrpfApplies: true),
        );

        expect($b->irpf)->toBeNull();
    });

    it('ordena el desglose de mayor a menor IVA', function () {
        $b = calculate([
            LineInput::of('1', '10', '4'),
            LineInput::of('1', '10', '21'),
            LineInput::of('1', '10', '10'),
        ]);

        expect(array_map(fn (TaxGroup $g) => $g->rate->value, $b->vatGroups()))->toBe(['21.00', '10.00', '4.00']);
    });
});

describe('rectificativas con importes negativos', function () {
    it('calcula un abono por diferencias con cantidad negativa', function () {
        $b = calculate(
            [new LineInput(Quantity::signed('-2'), UnitPrice::fromDecimal('40'), Percentage::zero(), Percentage::of('21'), Percentage::zero())],
            new TaxContext(allowNegativeQuantities: true),
        );

        expect(describeGroups($b))->toBe(['IVA 21.00: -80.00 / -16.80'])
            ->and((string) $b->total)->toBe('-96.80');
    });

    it('redondea los negativos alejándose de cero, simétrico a los positivos', function () {
        $abono = calculate([LineInput::of('-1', '10.005', '21')], new TaxContext(allowNegativeQuantities: true));
        $cargo = calculate([LineInput::of('1', '10.005', '21')]);

        expect($abono->total->cents)->toBe(-$cargo->total->cents);
    });
});

describe('invariantes', function () {
    it('el total siempre cuadra con el desglose', function (array $lines, TaxContext $context) {
        $b = calculate($lines, $context);

        $base = array_sum(array_map(fn (TaxGroup $g) => $g->base->cents, $b->vatGroups()));
        $expectedTotal = $b->taxableBase->cents + $b->vatTotal->cents + $b->surchargeTotal->cents - $b->irpfTotal->cents;

        expect($b->total->cents)->toBe($expectedTotal)
            ->and($b->taxableBase->cents)->toBe($base);

        foreach ($b->allGroups() as $group) {
            $exact = Money::fromDecimal($group->rate->applyTo($group->base->toDecimal()));

            expect($group->amount->cents)->toBe($exact->cents);
        }
    })->with([
        'mixto con IRPF y recargo' => [
            [
                LineInput::of('3', '33.333', '21', surchargeRate: '5.20', irpfApplies: true),
                LineInput::of('2.5', '12.345', '10', discount: '7.5', surchargeRate: '1.40'),
                LineInput::of('1', '99.999', '4', surchargeRate: '0.50'),
            ],
            new TaxContext(globalDiscount: Percentage::of('3'), irpfRate: Percentage::of('15'), customerSurchargeApplies: true, customerIrpfApplies: true),
        ],
        'muchas líneas pequeñas' => [
            array_fill(0, 37, LineInput::of('1', '0.337', '21')),
            new TaxContext,
        ],
    ]);

    it('marca el tipo de cada grupo', function () {
        $b = calculate(
            [LineInput::of('1', '100', '21', surchargeRate: '5.20', irpfApplies: true)],
            new TaxContext(irpfRate: Percentage::of('7'), customerSurchargeApplies: true, customerIrpfApplies: true),
        );

        expect(array_map(fn (TaxGroup $g) => $g->type, $b->allGroups()))
            ->toBe([TaxType::Vat, TaxType::Surcharge, TaxType::Irpf]);
    });
});
