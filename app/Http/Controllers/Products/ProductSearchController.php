<?php

declare(strict_types=1);

namespace App\Http\Controllers\Products;

use App\Domain\Catalog\Product;
use App\Http\Controllers\Controller;
use App\Http\Queries\CatalogIndexQuery;
use App\Http\Resources\ProductRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador incremental de productos activos para las líneas del documento:
 * como mucho 20, por nombre, referencia o descripción.
 */
final class ProductSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $products = CatalogIndexQuery::fromRequest($request)
            ->search(Product::query()->active(), ProductIndexController::SEARCH_COLUMNS)
            ->orderBy('name')
            ->limit(CatalogIndexQuery::SEARCH_LIMIT)
            ->get()
            ->map(fn (Product $product): array => (new ProductRow($product))->resolve($request));

        return response()->json($products);
    }
}
