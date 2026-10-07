<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documents\Document;
use App\Domain\Documents\Invoice;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Money;
use App\Http\Resources\DocumentRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de inicio: facturado este mes, pendiente y vencido, y los últimos
 * documentos. Los gráficos llegan con T113.
 */
final class DashboardController extends Controller
{
    /** Documentos que se muestran en "últimos documentos". */
    private const int RECENT_DOCUMENTS = 5;

    public function __invoke(Request $request, Clock $clock): Response
    {
        $today = $clock->today();

        $issuedThisMonth = Invoice::query()
            ->issued()
            ->whereBetween('issue_date', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()]);

        return Inertia::render('dashboard', [
            'stats' => [
                ...self::stat('issued_this_month', $issuedThisMonth),
                ...self::stat('pending', Invoice::query()->unpaid($today)),
                ...self::stat('overdue', Invoice::query()->overdue($today)),
            ],
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

    /**
     * `issued_this_month_count`, `issued_this_month_total` y `…_total_formatted`.
     *
     * @param  Builder<Invoice>  $query
     * @return array<string, int|string>
     */
    private static function stat(string $key, Builder $query): array
    {
        $total = Money::fromCents((int) (clone $query)->sum('total'));

        return [
            "{$key}_count" => (clone $query)->count(),
            "{$key}_total" => $total->cents,
            "{$key}_total_formatted" => $total->format(),
        ];
    }
}
