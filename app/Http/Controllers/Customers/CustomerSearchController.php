<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\Customer;
use App\Http\Controllers\Controller;
use App\Http\Queries\CatalogIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador incremental de clientes activos para el selector del documento:
 * como mucho 20, por nombre, nombre comercial, NIF o email.
 */
final class CustomerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $customers = CatalogIndexQuery::fromRequest($request)
            ->search(Customer::query()->active(), CustomerIndexController::SEARCH_COLUMNS)
            ->orderBy('legal_name')
            ->limit(CatalogIndexQuery::SEARCH_LIMIT)
            ->get()
            ->map(static fn (Customer $customer): array => [
                'id' => $customer->id,
                'legal_name' => $customer->legal_name,
                'trade_name' => $customer->trade_name,
                'tax_id' => $customer->tax_id,
                'email' => $customer->email,
                'irpf_applies' => $customer->appliesIrpf(),
                'surcharge_applies' => $customer->surcharge_applies,
            ]);

        return response()->json($customers);
    }
}
