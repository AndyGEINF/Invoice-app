<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Catalog\Data\ProductData;
use App\Domain\Catalog\Product;

/**
 * Crea o modifica un producto o servicio del catálogo.
 *
 * Cambiarlo no altera ningún documento: las líneas copian descripción, precio e
 * IVA al añadirse y `product_id` queda solo como referencia.
 */
final readonly class UpsertProduct
{
    public function __invoke(ProductData $data, ?Product $product = null): Product
    {
        $product ??= new Product;
        $product->fill($data->toAttributes())->save();

        return $product->refresh();
    }
}
