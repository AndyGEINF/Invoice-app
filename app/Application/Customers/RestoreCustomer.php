<?php

declare(strict_types=1);

namespace App\Application\Customers;

use App\Domain\Customers\Customer;

/** Devuelve un cliente archivado a los listados y buscadores. */
final readonly class RestoreCustomer
{
    public function __invoke(Customer $customer): Customer
    {
        if ($customer->isArchived()) {
            $customer->restore();
        }

        return $customer;
    }
}
