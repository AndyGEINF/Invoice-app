<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Money;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Queries\DocumentIndexQuery;
use App\Http\Resources\DocumentRow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado de facturas o rectificativas con filtros, totales de lo filtrado y
 * avisos (vencidas, borradores sin emitir).
 */
final class DocumentIndexController extends Controller
{
    use DocumentPageProps;

    /** Documentos que se enseñan en cada tarjeta de aviso. */
    private const int ALERT_ITEMS = 5;

    public function __invoke(Request $request, DocumentType $type, Clock $clock): Response
    {
        $today = $clock->today();
        $filters = DocumentIndexQuery::fromRequest($request);
        $filtered = $filters->apply(Document::classFor($type)::query(), $today);

        $documents = (clone $filtered)
            ->with('customer')
            // Borradores (sin fecha) arriba; después, lo más reciente primero.
            ->orderByRaw('issue_date DESC NULLS FIRST')
            ->orderByDesc('number')
            ->orderByDesc('created_at')
            ->paginate(DocumentIndexQuery::PER_PAGE)
            ->withQueryString()
            ->through(fn (Document $document): array => (new DocumentRow($document))->resolve($request));

        return Inertia::render('documents/index', [
            'type' => self::typeProps($type),
            'documents' => $documents,
            'filters' => $filters->toArray(),
            'totals' => DocumentIndexQuery::totals($filtered, $today),
            'alerts' => self::alerts($type, $today, $request),
            'series' => self::seriesOptions($type),
            // Opciones de los filtros con las etiquetas de los enums: una sola fuente.
            'options' => [
                'statuses' => self::options(DocumentStatus::cases()),
                'payment_statuses' => self::options(PaymentStatus::cases()),
            ],
        ]);
    }

    /**
     * Avisos sobre todos los documentos del tipo, sin filtros: lo que pide
     * atención aunque no se esté buscando.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function alerts(DocumentType $type, CarbonImmutable $today, Request $request): array
    {
        $query = Document::classFor($type)::query();

        return [
            'overdue' => self::alertGroup(
                (clone $query)->overdue($today)->orderBy('due_date'),
                $request,
            ),
            'drafts' => self::alertGroup(
                (clone $query)->where('status', DocumentStatus::Draft->value)->orderByDesc('updated_at'),
                $request,
            ),
        ];
    }

    /**
     * @param  Builder<covariant Document>  $query
     * @return array<string, mixed>
     */
    private static function alertGroup(Builder $query, Request $request): array
    {
        $sum = Money::fromCents((int) (clone $query)->reorder()->sum('total'));

        return [
            'count' => (clone $query)->reorder()->count(),
            'sum_formatted' => $sum->format(),
            'items' => (clone $query)
                ->with('customer')
                ->limit(self::ALERT_ITEMS)
                ->get()
                ->map(fn (Document $document): array => (new DocumentRow($document))->resolve($request))
                ->all(),
        ];
    }

    /**
     * @param  list<DocumentStatus|PaymentStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $cases): array
    {
        return array_map(static fn (DocumentStatus|PaymentStatus $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }
}
