<?php

declare(strict_types=1);

namespace App\Http\Controllers\Series;

use App\Application\Series\DeleteSeries;
use App\Application\Series\ToggleSeries;
use App\Application\Series\UpsertSeries;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Series;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\DocumentNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\SeriesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes > Series: las series de numeración de facturas, rectificativas y
 * presupuestos, con cuántos documentos tiene cada una y el número que saldrá
 * en la próxima emisión.
 */
final class SeriesController extends Controller
{
    public function index(Clock $clock): Response
    {
        $year = $clock->today()->year;

        $series = Series::query()
            ->withCount('documents')
            ->withCount(['documents as issued_count' => fn ($query) => $query->whereNotNull('number')])
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get();

        return Inertia::render('settings/series', [
            'series' => $series->map(static fn (Series $item): array => [
                'id' => $item->id,
                'document_type' => $item->document_type->value,
                'code' => $item->code,
                'prefix' => $item->prefix,
                'padding' => $item->padding,
                'next_number' => $item->nextNumberIn($year),
                'next_full_number' => $item->nextFullNumberIn($year),
                'resets_yearly' => $item->resets_yearly,
                'is_default' => $item->is_default,
                'is_active' => $item->is_active,
                'documents_count' => (int) $item->getAttribute('documents_count'),
                'has_issued' => (int) $item->getAttribute('issued_count') > 0,
            ])->all(),
            'types' => array_map(
                static fn (DocumentType $type): array => ['value' => $type->value, 'label' => $type->pluralLabel()],
                [DocumentType::Invoice, DocumentType::CreditNote, DocumentType::Quote],
            ),
            'limits' => [
                'min_padding' => DocumentNumber::MIN_PADDING,
                'max_padding' => DocumentNumber::MAX_PADDING,
                'default_padding' => DocumentNumber::DEFAULT_PADDING,
                'max_code_length' => SeriesRequest::MAX_CODE_LENGTH,
                'max_prefix_length' => SeriesRequest::MAX_PREFIX_LENGTH,
            ],
            'year' => $year,
        ]);
    }

    public function store(SeriesRequest $request, UpsertSeries $upsert): RedirectResponse
    {
        $series = $upsert(null, $request->toSeriesData());

        Inertia::flash('success', sprintf('Serie %s creada.', $series->code));

        return to_route('settings.series');
    }

    public function update(Series $series, SeriesRequest $request, UpsertSeries $upsert): RedirectResponse
    {
        $series = $upsert($series, $request->toSeriesData());

        Inertia::flash('success', sprintf('Serie %s guardada.', $series->code));

        return to_route('settings.series');
    }

    public function toggle(Series $series, ToggleSeries $toggle): RedirectResponse
    {
        $series = $toggle($series);

        Inertia::flash('success', sprintf($series->is_active ? 'Serie %s activada.' : 'Serie %s desactivada: ya no se podrá elegir al emitir.', $series->code));

        return to_route('settings.series');
    }

    public function destroy(Series $series, DeleteSeries $delete): RedirectResponse
    {
        $delete($series);

        Inertia::flash('success', sprintf('Serie %s borrada.', $series->code));

        return to_route('settings.series');
    }
}
