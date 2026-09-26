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

    /**
     * Con producto, los campos que la línea no trae (descripción, precio, IVA,
     * exención, unidad, retención) se copian de él. Lo que el usuario escribió
     * siempre prevalece: la línea es independiente del catálogo (decisión D4).
     *
     * @param  array<string, mixed>  $line
     */
    public static function fromArray(array $line, ?Product $product = null): self
    {
        $exemption = self::filled($line, 'exemption_code');

        return new self(
            position: (int) $line['position'],
            description: self::filled($line, 'description') ?? $product?->lineDescription() ?? '',
            quantity: Quantity::signed((string) $line['quantity']),
            unitPrice: self::filled($line, 'unit_price') !== null
                ? UnitPrice::fromDecimal((string) $line['unit_price'])
                : ($product?->unit_price ?? UnitPrice::zero()),
            discount: Percentage::of(self::filled($line, 'discount_percent') ?? Percentage::MIN),
            vatRate: self::filled($line, 'vat_rate') !== null
                ? Percentage::of((string) $line['vat_rate'])
                : ($product?->vat_rate ?? Percentage::zero()),
            surchargeRate: Percentage::of(self::filled($line, 'surcharge_rate') ?? Percentage::MIN),
            irpfApplies: array_key_exists('irpf_applies', $line)
                ? (bool) $line['irpf_applies']
                : ($product?->irpf_applicable ?? false),
            exemptionCode: $exemption !== null ? ExemptionCode::from($exemption) : $product?->exemption_code,
            unit: self::filled($line, 'unit') ?? $product?->unit ?? Product::DEFAULT_UNIT,
            productId: self::filled($line, 'product_id'),
            id: self::filled($line, 'id'),
        );
    }

    /** @param array<string, mixed> $line */
    private static function filled(array $line, string $key): ?string
    {
        $value = $line[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
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
