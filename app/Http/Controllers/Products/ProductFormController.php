<?php

declare(strict_types=1);

namespace App\Http\Controllers\Products;

use App\Application\Catalog\UpsertProduct;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta y edición de productos y servicios. Cambiar uno no altera ningún
 * documento: las líneas guardan su propia copia.
 */
final class ProductFormController extends Controller
{
    public function create(): Response
    {
        return self::form(null);
    }

    public function store(ProductRequest $request, UpsertProduct $upsert): RedirectResponse
    {
        $upsert($request->toProductData());

        Inertia::flash('success', 'Producto creado.');

        return to_route('products.index');
    }

    public function edit(Request $request, Product $product): Response
    {
        return self::form((new ProductRow($product))->resolve($request));
    }

    public function update(ProductRequest $request, Product $product, UpsertProduct $upsert): RedirectResponse
    {
        $upsert($request->toProductData(), $product);

        Inertia::flash('success', 'Producto guardado. Los documentos ya creados no cambian.');

        return to_route('products.index');
    }

    /** @param array<string, mixed>|null $product */
    private static function form(?array $product): Response
    {
        return Inertia::render('products/form', [
            'product' => $product,
            'options' => [
                'types' => array_map(
                    static fn (ProductType $type): array => ['value' => $type->value, 'label' => $type->label()],
                    ProductType::cases(),
                ),
                'default_unit' => Product::DEFAULT_UNIT,
                'default_vat_rate' => (string) config('invoice.tax.default_vat_rate'),
            ],
        ]);
    }
}
