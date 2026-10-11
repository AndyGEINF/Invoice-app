<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Money;
use App\Http\Queries\InvoiceTotals;
use App\Http\Resources\DocumentRow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de inicio: lo facturado este mes frente al anterior y su evolución en
 * los últimos doce meses, lo que queda por cobrar, los presupuestos abiertos,
 * lo que necesita atención y la actividad reciente.
 *
 * Todos los importes salen del servidor ya calculados y formateados: el
 * frontend solo los pinta.
 */
final class DashboardController extends Controller
{
    /** Documentos que se muestran en "actividad reciente". */
    private const int RECENT_DOCUMENTS = 6;

    /** Meses del gráfico de facturación, incluido el actual. */
    private const int CHART_MONTHS = 12;

    /** Un presupuesto enviado "caduca pronto" si le quedan como mucho estos días. */
    private const int EXPIRING_SOON_DAYS = 7;

    private const int PERCENT = 100;

    public function __invoke(Request $request, Clock $clock): Response
    {
        $today = $clock->today();
        $monthly = self::monthlyBilling($today);
        $current = $monthly[array_key_last($monthly)]['total'];
        $previous = $monthly[array_key_last($monthly) - 1]['total'];
        $pending = InvoiceTotals::stat('pending', Invoice::query()->unpaid($today));
        $overdue = InvoiceTotals::stat('overdue', Invoice::query()->overdue($today));
        $outstanding = (int) $pending['pending_total'] + (int) $overdue['overdue_total'];

        return Inertia::render('dashboard', [
            'stats' => [
                ...InvoiceTotals::stat('issued_this_month', self::issuedInMonth($today)),
                ...$pending,
                ...$overdue,
                'previous_month_total_formatted' => Money::fromCents($previous)->format(),
                // Variación entera frente al mes anterior; null si el anterior fue cero.
                'change_percent' => $previous > 0 ? intdiv(($current - $previous) * self::PERCENT, $previous) : null,
                // Todo lo emitido sin cobrar, y qué parte de ello está vencida (0–100).
                'outstanding_total_formatted' => Money::fromCents($outstanding)->format(),
                'overdue_share_percent' => $outstanding > 0 ? intdiv((int) $overdue['overdue_total'] * self::PERCENT, $outstanding) : 0,
            ],
            'monthly' => $monthly,
            'quotes' => self::quotes($today),
            'attention' => self::attention($today),
            'recent' => Document::query()
                ->with('customer')
                ->latest('updated_at')
                ->limit(self::RECENT_DOCUMENTS)
                ->get()
                ->map(fn (Document $document): array => (new DocumentRow($document))->resolve($request))
                ->all(),
            'issuer_incomplete' => ! Issuer::current()->isComplete(),
        ]);
    }

    /** @return Builder<Invoice> */
    private static function issuedInMonth(CarbonImmutable $day): Builder
    {
        return Invoice::query()
            ->issued()
            ->whereBetween('issue_date', [$day->startOfMonth()->toDateString(), $day->endOfMonth()->toDateString()]);
    }

    /**
     * Facturas emitidas por mes, de más antiguo a más reciente, con los meses
     * sin facturas a cero para que el gráfico no tenga huecos.
     *
     * @return list<array{month: string, total: int, total_formatted: string, invoices: int}>
     */
    private static function monthlyBilling(CarbonImmutable $today): array
    {
        $first = $today->startOfMonth()->subMonths(self::CHART_MONTHS - 1);

        $rows = Invoice::query()
            ->issued()
            ->where('issue_date', '>=', $first->toDateString())
            ->where('issue_date', '<=', $today->endOfMonth()->toDateString())
            ->selectRaw("to_char(issue_date, 'YYYY-MM') as month, sum(total) as amount, count(*) as invoices")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = [];

        for ($i = 0; $i < self::CHART_MONTHS; $i++) {
            $key = $first->addMonths($i)->format('Y-m');
            $row = $rows->get($key);
            $total = (int) ($row?->getAttribute('amount') ?? 0);

            $months[] = [
                'month' => $key,
                'total' => $total,
                'total_formatted' => Money::fromCents($total)->format(),
                'invoices' => (int) ($row?->getAttribute('invoices') ?? 0),
            ];
        }

        return $months;
    }

    /**
     * Presupuestos vivos: enviados aún en plazo y aceptados sin facturar.
     *
     * @return array<string, int|string>
     */
    private static function quotes(CarbonImmutable $today): array
    {
        $open = Quote::query()
            ->where(fn (Builder $query) => $query
                ->where('status', QuoteStatus::Accepted->value)
                ->orWhere(fn (Builder $sent) => $sent
                    ->where('status', QuoteStatus::Sent->value)
                    ->where('valid_until', '>=', $today->toDateString())));

        return InvoiceTotals::stat('open', $open);
    }

    /**
     * Lo que pide hacer algo hoy. Cada grupo trae cuántos son y cuánto suman;
     * el texto lo pone el frontend. Los grupos vacíos no se envían.
     *
     * @return list<array{kind: string, count: int, total_formatted: string}>
     */
    private static function attention(CarbonImmutable $today): array
    {
        $groups = [
            'overdue_invoices' => Invoice::query()->overdue($today),
            'accepted_quotes' => Quote::query()->where('status', QuoteStatus::Accepted->value),
            'expiring_quotes' => Quote::query()
                ->where('status', QuoteStatus::Sent->value)
                ->whereBetween('valid_until', [$today->toDateString(), $today->addDays(self::EXPIRING_SOON_DAYS)->toDateString()]),
            'draft_invoices' => Invoice::query()->where('status', DocumentStatus::Draft->value),
        ];

        $items = [];

        foreach ($groups as $kind => $query) {
            $count = (clone $query)->count();

            if ($count > 0) {
                $items[] = [
                    'kind' => $kind,
                    'count' => $count,
                    'total_formatted' => Money::fromCents((int) (clone $query)->sum('total'))->format(),
                ];
            }
        }

        return $items;
    }
}
