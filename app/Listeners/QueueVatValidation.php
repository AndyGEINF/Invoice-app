<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Customers\Customer;
use App\Events\CustomerSaved;
use App\Jobs\ValidateVatNumberJob;

/**
 * Al guardar un cliente con número de IVA intracomunitario, encola su
 * validación en VIES. El job sale tras el commit y decide si hace falta
 * consultar (no repite una validación reciente).
 */
final readonly class QueueVatValidation
{
    public function handle(CustomerSaved $event): void
    {
        $customer = Customer::query()->find($event->customerId);

        if ($customer?->taxId()?->isEuVat() !== true) {
            return;
        }

        ValidateVatNumberJob::dispatch($customer->id);
    }
}
