<?php

declare(strict_types=1);

namespace App\Application\Catalog\Data;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Product;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\UnitPrice;

/**
 * Datos de alta o edición de un producto o servicio, ya validados.
 *
 * El precio llega como texto decimal de hasta tres decimales ("33.333") y se
 * guarda en milésimas sin pasar nunca por un float.
 */
final readonly class ProductData
{
    public function __construct(
        public ProductType $type,
        public string $name,
        public UnitPrice $unitPrice,
        public Percentage $vatRate,
        public ?string $sku = null,
        public ?string $description = null,
        public string $unit = Product::DEFAULT_UNIT,
        public ?ExemptionCode $exemptionCode = null,
        public bool $irpfApplicable = false,
    ) {}

    /** @param array<string, mixed> $form */
    public static function fromArray(array $form): self
    {
        $exemption = self::stringOrNull($form['exemption_code'] ?? null);
        $sku = self::stringOrNull($form['sku'] ?? null);

        return new self(
            type: ProductType::from((string) $form['type']),
            name: trim((string) $form['name']),
            unitPrice: UnitPrice::fromDecimal(str_replace(',', '.', trim((string) $form['unit_price']))),
            // Una línea exenta no lleva IVA: el tipo se guarda a cero.
            vatRate: $exemption !== null ? Percentage::zero() : Percentage::of((string) $form['vat_rate']),
            sku: $sku !== null ? strtoupper($sku) : null,
            description: self::stringOrNull($form['description'] ?? null),
            unit: self::stringOrNull($form['unit'] ?? null) ?? Product::DEFAULT_UNIT,
            exemptionCode: $exemption !== null ? ExemptionCode::from($exemption) : null,
            irpfApplicable: (bool) ($form['irpf_applicable'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'unit_price' => $this->unitPrice,
            'vat_rate' => $this->vatRate,
            'sku' => $this->sku,
            'description' => $this->description,
            'unit' => $this->unit,
            'exemption_code' => $this->exemptionCode,
            'irpf_applicable' => $this->irpfApplicable,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
