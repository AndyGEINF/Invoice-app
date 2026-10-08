<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\Customer;
use App\Http\Controllers\Controller;
use App\Http\Queries\CatalogIndexQuery;
use App\Http\Resources\CustomerRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Listado de clientes activos (o archivados) con búsqueda por nombre, NIF o email. */
final class CustomerIndexController extends Controller
{
    /** Columnas en las que busca el texto del listado y del buscador. */
    public const array SEARCH_COLUMNS = ['legal_name', 'trade_name', 'tax_id', 'email'];

    public function __invoke(Request $request): Response
    {
        $filters = CatalogIndexQuery::fromRequest($request);

        $customers = $filters->apply(Customer::query(), self::SEARCH_COLUMNS)
            ->with('contacts')
            ->orderBy('legal_name')
            ->paginate(CatalogIndexQuery::PER_PAGE)
            ->withQueryString()
            ->through(fn (Customer $customer): array => (new CustomerRow($customer))->resolve($request));

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => $filters->toArray(),
            'counts' => [
                'active' => Customer::query()->active()->count(),
                'archived' => Customer::query()->archived()->count(),
            ],
        ]);
    }
}
