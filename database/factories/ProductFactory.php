<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Product;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\UnitPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** Precio máximo aleatorio, en milésimas (1.000,000 €). */
    private const int MAX_PRICE_THOUSANDTHS = 1_000_000;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('???-####')),
            'type' => ProductType::Product,
            'name' => ucfirst($this->faker->words(3, true)),
            'unit_price' => UnitPrice::fromThousandths($this->faker->numberBetween(1, self::MAX_PRICE_THOUSANDTHS)),
            'unit' => Product::DEFAULT_UNIT,
            'vat_rate' => '21.00',
            'irpf_applicable' => false,
        ];
    }

    /** Servicio por horas: el caso típico con IRPF. */
    public function service(): self
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Service,
            'unit' => 'h',
            'irpf_applicable' => true,
        ]);
    }

    public function priced(string $unitPrice): self
    {
        return $this->state(fn (): array => ['unit_price' => UnitPrice::fromDecimal($unitPrice)]);
    }

    public function vat(string $rate): self
    {
        return $this->state(fn (): array => ['vat_rate' => $rate]);
    }

    public function exempt(ExemptionCode $code = ExemptionCode::E1): self
    {
        return $this->state(fn (): array => ['vat_rate' => '0.00', 'exemption_code' => $code]);
    }

    public function archived(): self
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
