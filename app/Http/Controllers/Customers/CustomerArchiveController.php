<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Application\Customers\ArchiveCustomer;
use App\Application\Customers\RestoreCustomer;
use App\Domain\Customers\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Archivar y restaurar clientes. Un cliente nunca se borra. */
final class CustomerArchiveController extends Controller
{
    public function archive(Customer $customer, ArchiveCustomer $archive): RedirectResponse
    {
        $archive($customer);

        Inertia::flash('success', 'Cliente archivado. Sus documentos no cambian.');

        return to_route('customers.show', $customer);
    }

    public function restore(Customer $customer, RestoreCustomer $restore): RedirectResponse
    {
        $restore($customer);

        Inertia::flash('success', 'Cliente restaurado.');

        return to_route('customers.show', $customer);
    }
}
