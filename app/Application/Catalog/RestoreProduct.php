<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Product;

/** Devuelve un producto archivado al catálogo. */
final readonly class RestoreProduct
{
    public function __invoke(Product $product): Product
    {
        if ($product->isArchived()) {
            $product->restore();
        }

        return $product;
    }
}
