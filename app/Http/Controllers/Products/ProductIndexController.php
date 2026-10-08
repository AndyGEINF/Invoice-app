<?php

declare(strict_types=1);

namespace App\Http\Controllers\Products;

use App\Domain\Catalog\Product;
use App\Http\Controllers\Controller;
use App\Http\Queries\CatalogIndexQuery;
use App\Http\Resources\ProductRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Catálogo de productos y servicios activos (o archivados), con búsqueda. */
final class ProductIndexController extends Controller
{
    /** Columnas en las que busca el texto del listado y del buscador. */
    public const array SEARCH_COLUMNS = ['name', 'sku', 'description'];

    public function __invoke(Request $request): Response
    {
        $filters = CatalogIndexQuery::fromRequest($request);

        $products = $filters->apply(Product::query(), self::SEARCH_COLUMNS)
            ->orderBy('name')
            ->paginate(CatalogIndexQuery::PER_PAGE)
            ->withQueryString()
            ->through(fn (Product $product): array => (new ProductRow($product))->resolve($request));

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => $filters->toArray(),
            'counts' => [
                'active' => Product::query()->active()->count(),
                'archived' => Product::query()->whereNotNull('archived_at')->count(),
            ],
        ]);
    }
}
