<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\Customer;
use App\Http\Controllers\Controller;
use App\Jobs\ValidateVatNumberJob;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * "Comprobar en VIES" desde la ficha: encola la validación aunque se hiciera
 * hace poco. El resultado llega cuando el job termina; nunca bloquea.
 */
final class ValidateVatController extends Controller
{
    public function __invoke(Customer $customer): RedirectResponse
    {
        if ($customer->taxId()?->isEuVat() !== true) {
            Inertia::flash('error', 'Solo se comprueban en VIES los números de IVA de otros países de la UE.');

            return to_route('customers.show', $customer);
        }

        ValidateVatNumberJob::dispatch($customer->id, force: true);

        Inertia::flash('success', 'Comprobando el número en VIES. El resultado aparecerá en la ficha en unos segundos.');

        return to_route('customers.show', $customer);
    }
}
