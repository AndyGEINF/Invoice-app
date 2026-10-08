<?php

declare(strict_types=1);

namespace App\Application\Customers;

use App\Domain\Customers\Customer;

/**
 * Resultado de guardar un cliente: o se guardó, o no se guardó porque ya existe
 * otro cliente con el mismo identificador fiscal y hay que confirmar.
 */
final readonly class UpsertResult
{
    private function __construct(
        public ?Customer $customer,
        public ?Customer $duplicate,
    ) {}

    public static function saved(Customer $customer): self
    {
        return new self($customer, null);
    }

    public static function duplicateOf(Customer $existing): self
    {
        return new self(null, $existing);
    }

    public function isDuplicate(): bool
    {
        return $this->duplicate !== null;
    }
}
