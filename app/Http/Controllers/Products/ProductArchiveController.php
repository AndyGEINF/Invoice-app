<?php

declare(strict_types=1);

namespace App\Http\Controllers\Products;

use App\Application\Catalog\ArchiveProduct;
use App\Application\Catalog\RestoreProduct;
use App\Domain\Catalog\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Archivar y restaurar productos. Las líneas que ya los usan no cambian. */
final class ProductArchiveController extends Controller
{
    public function archive(Product $product, ArchiveProduct $archive): RedirectResponse
    {
        $archive($product);

        Inertia::flash('success', 'Producto archivado.');

        return back();
    }

    public function restore(Product $product, RestoreProduct $restore): RedirectResponse
    {
        $restore($product);

        Inertia::flash('success', 'Producto restaurado.');

        return back();
    }
}
