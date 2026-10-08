<?php

declare(strict_types=1);

namespace App\Application\Customers;

use App\Domain\Customers\Customer;

/**
 * Archiva un cliente: deja de salir en listados y buscadores, pero sus
 * documentos lo siguen referenciando. Los clientes no se borran nunca, porque
 * las facturas emitidas apuntan a ellos.
 */
final readonly class ArchiveCustomer
{
    public function __invoke(Customer $customer): Customer
    {
        if (! $customer->isArchived()) {
            $customer->archive();
        }

        return $customer;
    }
}
