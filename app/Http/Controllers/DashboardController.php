<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documents\Document;
use App\Domain\Documents\Invoice;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Http\Queries\InvoiceTotals;
use App\Http\Resources\DocumentRow;
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
                ...InvoiceTotals::stat('issued_this_month', $issuedThisMonth),
                ...InvoiceTotals::stat('pending', Invoice::query()->unpaid($today)),
                ...InvoiceTotals::stat('overdue', Invoice::query()->overdue($today)),
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
}
