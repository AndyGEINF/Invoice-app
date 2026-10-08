<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Catalog\Data\ProductData;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Product;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Http\Requests\Concerns\NormalizesPercentages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formulario de producto o servicio. El precio llega como texto con
 * hasta tres decimales; la referencia (SKU) es única si se indica.
 */
final class ProductRequest extends FormRequest
{
    use NormalizesPercentages;

    public const int MAX_SKU_LENGTH = 50;

    public const int MAX_NAME_LENGTH = 200;

    public const int MAX_DESCRIPTION_LENGTH = 2000;

    public const int MAX_UNIT_LENGTH = 20;

    /** Precio unitario en euros con hasta 3 decimales (milésimas). */
    private const string UNIT_PRICE_PATTERN = '/^\d{1,10}(\.\d{1,3})?$/';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => [
                'nullable',
                'string',
                'max:'.self::MAX_SKU_LENGTH,
                Rule::unique('products', 'sku')->ignore($product instanceof Product ? $product->id : null),
            ],
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'description' => ['nullable', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            'unit_price' => ['required', 'regex:'.self::UNIT_PRICE_PATTERN],
            'unit' => ['nullable', 'string', 'max:'.self::MAX_UNIT_LENGTH],
            'vat_rate' => ['required', Rule::in(config('invoice.tax.vat_rates'))],
            'exemption_code' => ['nullable', Rule::enum(ExemptionCode::class)],
            'irpf_applicable' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sku' => 'referencia',
            'type' => 'tipo',
            'name' => 'nombre',
            'description' => 'descripción',
            'unit_price' => 'precio',
            'unit' => 'unidad',
            'vat_rate' => 'IVA',
            'exemption_code' => 'causa de exención',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sku.unique' => 'Ya hay otro producto con esta referencia.',
            'unit_price.regex' => 'El precio debe ser un importe positivo con hasta tres decimales.',
        ];
    }

    public function toProductData(): ProductData
    {
        return ProductData::fromArray($this->validated());
    }

    /** La referencia se compara en mayúsculas; precio e IVA admiten coma decimal. */
    protected function prepareForValidation(): void
    {
        $sku = $this->input('sku');
        $price = $this->input('unit_price');

        $this->merge(array_filter([
            'sku' => is_string($sku) ? strtoupper(trim($sku)) : null,
            'unit_price' => is_string($price) ? str_replace(',', '.', trim($price)) : null,
            'vat_rate' => $this->has('vat_rate') ? self::normalizePercentage($this->input('vat_rate')) : null,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
