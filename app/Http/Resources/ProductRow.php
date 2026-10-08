<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Product;
use App\Domain\Shared\Currency;
use App\Http\Presenters\SpanishFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Producto o servicio para listados, formularios y el buscador de líneas.
 * El precio va como texto decimal de tres cifras ("33.333") y, aparte, ya
 * formateado para mostrar.
 *
 * @property Product $resource
 */
final class ProductRow extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->resource;

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'type' => $product->type->value,
            'type_label' => $product->type->label(),
            'name' => $product->name,
            'description' => $product->description,
            'unit_price' => $product->unit_price->toDecimalString(),
            'unit_price_formatted' => SpanishFormat::unitPrice($product->unit_price, Currency::EUR),
            'unit' => $product->unit,
            'vat_rate' => (string) $product->vat_rate,
            'exemption_code' => $product->exemption_code?->value,
            'irpf_applicable' => $product->irpf_applicable,
            'is_archived' => $product->isArchived(),
        ];
    }
}
