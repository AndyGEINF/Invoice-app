<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Money;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLine>
 */
final class DocumentLineFactory extends Factory
{
    protected $model = DocumentLine::class;

    public function definition(): array
    {
        return [
            'document_id' => Invoice::factory(),
            'position' => 1,
            'description' => ucfirst($this->faker->words(4, true)),
            'quantity' => Quantity::of('1'),
            'unit' => 'ud',
            'unit_price' => UnitPrice::fromDecimal('100'),
            'discount_percent' => '0.00',
            'vat_rate' => '21.00',
            'surcharge_rate' => '0.00',
            'irpf_applies' => false,
            'line_base' => Money::fromCents(10000),
        ];
    }

    /** Línea con cantidad, precio y tipo de IVA concretos. */
    public function of(string $quantity, string $unitPrice, string $vatRate = '21.00'): self
    {
        return $this->state(fn (): array => [
            'quantity' => Quantity::of($quantity),
            'unit_price' => UnitPrice::fromDecimal($unitPrice),
            'vat_rate' => $vatRate,
        ]);
    }
}
