<?php

declare(strict_types=1);

namespace App\Application\Customers;

use App\Domain\Customers\Customer;

/**
 * Notas del cliente: un único bloque de texto que solo ve el usuario (no sale
 * en ningún documento). Se guardan aparte del formulario de edición.
 */
final readonly class UpdateCustomerNotes
{
    public function __invoke(Customer $customer, ?string $notes): void
    {
        $notes = $notes === null || trim($notes) === '' ? null : $notes;

        if ($customer->notes === $notes) {
            return;
        }

        $customer->notes = $notes;
        $customer->save();
    }
}
