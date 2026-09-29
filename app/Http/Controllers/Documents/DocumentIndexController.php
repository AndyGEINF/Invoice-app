<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Shared\Contracts\Clock;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Queries\DocumentIndexQuery;
use App\Http\Resources\DocumentRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Listado de facturas o rectificativas con filtros y totales de lo filtrado.
 */
final class DocumentIndexController extends Controller
{
    use DocumentPageProps;

    public function __invoke(Request $request, DocumentType $type, Clock $clock): Response
    {
        $filters = DocumentIndexQuery::fromRequest($request);
        $filtered = $filters->apply(Document::classFor($type)::query(), $clock->today());

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
            'totals' => DocumentIndexQuery::totals($filtered),
            'series' => self::seriesOptions($type),
            // Opciones de los filtros con las etiquetas de los enums: una sola fuente.
            'options' => [
                'statuses' => self::options(DocumentStatus::cases()),
                'payment_statuses' => self::options(PaymentStatus::cases()),
            ],
        ]);
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
