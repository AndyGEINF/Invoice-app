<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\Customer;
use App\Domain\Documents\Document;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Money;
use App\Http\Controllers\Controller;
use App\Http\Queries\CatalogIndexQuery;
use App\Http\Queries\InvoiceTotals;
use App\Http\Resources\CustomerRow;
use App\Http\Resources\DocumentRow;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado de clientes: tarjetas de resumen, tabla con lo pendiente de cada uno,
 * panel con el cliente seleccionado (`?selected=`) y, debajo, los que más
 * facturan este año y los documentos pendientes de cobro.
 */
final class CustomerIndexController extends Controller
{
    /** Columnas en las que busca el texto del listado y del buscador. */
    public const array SEARCH_COLUMNS = ['legal_name', 'trade_name', 'tax_id', 'email'];

    /** Clientes en "Top clientes por facturación". */
    private const int TOP_CUSTOMERS = 5;

    /** Documentos en "Documentos pendientes" y en la actividad del cliente seleccionado. */
    private const int LIST_ITEMS = 5;

    private const int PERCENT = 100;

    public function __invoke(Request $request, Clock $clock): Response
    {
        $today = $clock->today();
        $filters = CatalogIndexQuery::fromRequest($request);

        $page = $filters->apply(Customer::query(), self::SEARCH_COLUMNS)
            ->with('contacts')
            ->orderBy('legal_name')
            ->paginate(CatalogIndexQuery::PER_PAGE)
            ->withQueryString();

        /** @var list<string> $ids */
        $ids = $page->getCollection()->pluck('id')->all();
        $amounts = InvoiceTotals::unpaidByCustomer($ids, $today);

        $customers = $page->through(fn (Customer $customer): array => [
            ...(new CustomerRow($customer))->resolve($request),
            ...InvoiceTotals::unpaidProps($amounts[$customer->id]),
        ]);

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => $filters->toArray(),
            'counts' => [
                'active' => Customer::query()->active()->count(),
                'archived' => Customer::query()->archived()->count(),
            ],
            'stats' => [
                ...InvoiceTotals::stat('pending', Invoice::query()->unpaid($today)),
                ...InvoiceTotals::stat('overdue', Invoice::query()->overdue($today)),
            ],
            'top_customers' => self::topCustomers($today),
            'pending_documents' => Invoice::query()
                ->outstanding()
                ->with('customer')
                ->orderBy('due_date')
                ->limit(self::LIST_ITEMS)
                ->get()
                ->map(fn (Invoice $invoice): array => (new DocumentRow($invoice))->resolve($request))
                ->all(),
            'selected' => fn (): ?array => self::selected($request, $today),
        ]);
    }

    /**
     * Los que más se les ha facturado este año (facturas emitidas), con su peso
     * sobre el primero para pintar la barra.
     *
     * @return list<array<string, mixed>>
     */
    private static function topCustomers(CarbonImmutable $today): array
    {
        $rows = InvoiceTotals::issuedThisYear($today)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, sum(total) as billed, count(*) as invoices')
            ->orderByDesc('billed')
            ->limit(self::TOP_CUSTOMERS)
            ->get();

        $customers = Customer::query()->whereKey($rows->pluck('customer_id'))->get()->keyBy('id');
        $max = (int) ($rows->first()?->getAttribute('billed') ?? 0);

        return $rows
            ->map(static function (Invoice $row) use ($customers, $max): array {
                $billed = Money::fromCents((int) $row->getAttribute('billed'));
                $customer = $customers->get($row->customer_id);

                return [
                    'id' => $row->customer_id,
                    'display_name' => $customer?->displayName() ?? '—',
                    'invoices' => (int) $row->getAttribute('invoices'),
                    'billed_total' => $billed->cents,
                    'billed_total_formatted' => $billed->format(),
                    // Ancho de la barra respecto al primero, en porcentaje entero.
                    'share_percent' => $max > 0 ? intdiv($billed->cents * self::PERCENT, $max) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resumen del cliente elegido en la tabla: sus datos, lo que debe y su
     * actividad reciente. El frontend lo pide con una recarga parcial.
     *
     * @return array<string, mixed>|null
     */
    private static function selected(Request $request, CarbonImmutable $today): ?array
    {
        $id = $request->query('selected');

        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        $customer = Customer::query()->with('contacts')->find($id);

        if ($customer === null) {
            return null;
        }

        $billedThisYear = Money::fromCents((int) InvoiceTotals::issuedThisYear($today, $customer->id)->sum('total'));

        return [
            ...(new CustomerRow($customer))->resolve($request),
            ...InvoiceTotals::unpaidProps(InvoiceTotals::unpaidByCustomer([$customer->id], $today)[$customer->id]),
            'billed_year_total_formatted' => $billedThisYear->format(),
            'notes' => $customer->notes,
            'recent_documents' => Document::query()
                ->where('customer_id', $customer->id)
                ->with('customer')
                ->latest('updated_at')
                ->limit(self::LIST_ITEMS)
                ->get()
                ->map(fn (Document $document): array => (new DocumentRow($document))->resolve($request))
                ->all(),
        ];
    }
}
