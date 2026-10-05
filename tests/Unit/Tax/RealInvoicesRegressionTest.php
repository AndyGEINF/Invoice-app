<?php

declare(strict_types=1);

use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Enums\VatRegime;
use App\Domain\Shared\Percentage;
use App\Domain\Tax\LineInput;
use App\Domain\Tax\SpanishTaxCalculator;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxGroup;

/*
 * SC-002: el motor reproduce al céntimo el desglose de facturas reales o
 * típicas. Los casos y su cálculo a mano están en
 * tests/Fixtures/tax/real-invoices.json; para añadir uno basta con escribirlo
 * allí (T102 lo amplía a 50 facturas reales anonimizadas).
 */

const REAL_INVOICES_FIXTURE = __DIR__.'/../../Fixtures/tax/real-invoices.json';

/** Mínimo de casos que exige la tarea; T102 lo sube a 50. */
const MIN_REAL_INVOICE_CASES = 10;

/** @return array<string, array{0: array<string, mixed>}> */
function realInvoiceCases(): array
{
    /** @var array{cases: list<array<string, mixed>>} $fixture */
    $fixture = json_decode((string) file_get_contents(REAL_INVOICES_FIXTURE), true, flags: JSON_THROW_ON_ERROR);

    $cases = [];

    foreach ($fixture['cases'] as $case) {
        $cases["{$case['id']} · {$case['description']}"] = [$case];
    }

    return $cases;
}

/** "IVA 21.00 E1: 600.00 / 0.00" — el orden de los grupos no importa. */
function groupSignature(string $type, string $rate, ?string $exemption, string $base, string $amount): string
{
    return trim("{$type} {$rate} ".($exemption ?? '')).": {$base} / {$amount}";
}

it('el fichero tiene al menos los casos iniciales', function () {
    expect(count(realInvoiceCases()))->toBeGreaterThanOrEqual(MIN_REAL_INVOICE_CASES);
});

it('reproduce al céntimo el desglose de la factura', function (array $case) {
    $context = $case['context'];

    $lines = array_map(
        static fn (array $line): LineInput => LineInput::of(
            quantity: $line['quantity'],
            unitPrice: $line['unit_price'],
            vatRate: $line['vat_rate'],
            discount: $line['discount'] ?? '0',
            surchargeRate: $line['surcharge_rate'] ?? '0',
            irpfApplies: $line['irpf'] ?? false,
            exemptionCode: isset($line['exemption']) ? ExemptionCode::from($line['exemption']) : null,
        ),
        $case['lines'],
    );

    $breakdown = (new SpanishTaxCalculator)->calculate($lines, new TaxContext(
        globalDiscount: Percentage::of($context['global_discount'] ?? '0'),
        irpfRate: Percentage::of($context['irpf_rate'] ?? '0'),
        issuerRegime: VatRegime::from($context['issuer_regime'] ?? VatRegime::General->value),
        customerSurchargeApplies: $context['customer_surcharge'] ?? false,
        customerIrpfApplies: $context['customer_irpf'] ?? false,
    ));

    $actual = array_map(
        static fn (TaxGroup $group): string => groupSignature($group->type->value, $group->rate->value, $group->exemptionCode?->value, (string) $group->base, (string) $group->amount),
        $breakdown->allGroups(),
    );
    $expected = array_map(
        static fn (array $group): string => groupSignature($group['type'], $group['rate'], $group['exemption'] ?? null, $group['base'], $group['amount']),
        $case['expected']['groups'],
    );
    sort($actual);
    sort($expected);

    expect($actual)->toBe($expected)
        ->and((string) $breakdown->taxableBase)->toBe($case['expected']['taxable_base'])
        ->and((string) $breakdown->vatTotal)->toBe($case['expected']['vat_total'])
        ->and((string) $breakdown->surchargeTotal)->toBe($case['expected']['surcharge_total'])
        ->and((string) $breakdown->irpfTotal)->toBe($case['expected']['irpf_total'])
        ->and((string) $breakdown->total)->toBe($case['expected']['total']);
})->with(realInvoiceCases());
