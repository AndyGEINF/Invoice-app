<?php

declare(strict_types=1);

namespace App\Application\Documents\Data;

use App\Application\Documents\RecalculateDocument;
use App\Domain\Catalog\Product;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use App\Domain\Tax\LineInput;

/**
 * Una línea del formulario de borrador (`DraftForm.lines[]` de
 * contracts/web-routes.md), ya validada y convertida a value objects.
 *
 * La cantidad se admite con signo: solo una rectificativa puede usar negativos
 * y eso lo decide el caso de uso, no el formulario.
 */
final readonly class LineData
{
    public function __construct(
        public int $position,
        public string $description,
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Percentage $discount,
        public Percentage $vatRate,
        public Percentage $surchargeRate,
        public bool $irpfApplies = false,
        public ?ExemptionCode $exemptionCode = null,
        public string $unit = Product::DEFAULT_UNIT,
        public ?string $productId = null,
        public ?string $id = null,
    ) {}

    /** @param array<string, mixed> $line */
    public static function fromArray(array $line): self
    {
        $exemption = $line['exemption_code'] ?? null;

        return new self(
            position: (int) $line['position'],
            description: (string) $line['description'],
            quantity: Quantity::signed((string) $line['quantity']),
            unitPrice: UnitPrice::fromDecimal((string) $line['unit_price']),
            discount: Percentage::of((string) ($line['discount_percent'] ?? Percentage::MIN)),
            vatRate: Percentage::of((string) $line['vat_rate']),
            surchargeRate: Percentage::of((string) ($line['surcharge_rate'] ?? Percentage::MIN)),
            irpfApplies: (bool) ($line['irpf_applies'] ?? false),
            exemptionCode: $exemption === null || $exemption === '' ? null : ExemptionCode::from((string) $exemption),
            unit: (string) ($line['unit'] ?? Product::DEFAULT_UNIT),
            productId: isset($line['product_id']) && $line['product_id'] !== '' ? (string) $line['product_id'] : null,
            id: isset($line['id']) && $line['id'] !== '' ? (string) $line['id'] : null,
        );
    }

    /**
     * Columnas de `document_lines`. `line_base` lo escribe
     * {@see RecalculateDocument}.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'position' => $this->position,
            'product_id' => $this->productId,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unitPrice,
            'discount_percent' => $this->discount,
            'vat_rate' => $this->vatRate,
            'surcharge_rate' => $this->surchargeRate,
            'irpf_applies' => $this->irpfApplies,
            'exemption_code' => $this->exemptionCode,
        ];
    }

    public function toLineInput(): LineInput
    {
        return new LineInput(
            $this->quantity,
            $this->unitPrice,
            $this->discount,
            $this->vatRate,
            $this->surchargeRate,
            $this->irpfApplies,
            $this->exemptionCode,
        );
    }
}
