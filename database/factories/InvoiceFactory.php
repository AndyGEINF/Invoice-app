<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documents\Invoice;

/**
 * @extends DocumentFactory<Invoice>
 */
final class InvoiceFactory extends DocumentFactory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [...parent::definition(), 'type' => Invoice::fixedType()];
    }
}
