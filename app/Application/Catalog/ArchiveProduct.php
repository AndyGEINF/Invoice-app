<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Product;

/**
 * Archiva un producto: deja de salir en el catálogo y en el buscador de líneas.
 * Las líneas que ya lo usaron conservan sus datos copiados.
 */
final readonly class ArchiveProduct
{
    public function __invoke(Product $product): Product
    {
        if (! $product->isArchived()) {
            $product->archive();
        }

        return $product;
    }
}
