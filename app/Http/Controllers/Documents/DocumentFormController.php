<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\CreateDraft;
use App\Application\Documents\DeleteDraft;
use App\Application\Documents\UpdateDraft;
use App\Domain\Customers\Customer;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Issuer\Issuer;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Requests\DraftDocumentRequest;
use App\Http\Resources\DraftFormResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta, edición y borrado de borradores. Cada guardado recalcula en el servidor
 * y vuelve al formulario con el desglose nuevo: el frontend nunca calcula.
 */
final class DocumentFormController extends Controller
{
    use DocumentPageProps;

    public function create(Request $request, DocumentType $type): Response
    {
        $issuer = Issuer::current();
        $customerId = $request->query('customer_id');
        $customerId = is_string($customerId) && Str::isUuid($customerId) && Customer::query()->whereKey($customerId)->exists()
            ? $customerId
            : null;

        return Inertia::render('documents/form', [
            'type' => self::typeProps($type),
            'document' => DraftFormResource::blank($issuer->default_currency, $issuer->default_irpf_rate, $customerId),
            'series' => self::seriesOptions($type),
            'defaults' => self::defaults($issuer),
        ]);
    }

    public function store(DraftDocumentRequest $request, DocumentType $type, CreateDraft $createDraft): RedirectResponse
    {
        $document = $createDraft($type, $request->toDraftData());

        Inertia::flash('success', 'Borrador guardado.');

        return to_route('documents.edit', self::routeParams($type, $document));
    }

    public function edit(Request $request, DocumentType $type, Document $document): Response
    {
        if (! $document->isEditable()) {
            throw DocumentIsImmutable::notEditable($document);
        }

        $document->load(['lines', 'taxes']);

        return Inertia::render('documents/form', [
            'type' => self::typeProps($type),
            'document' => (new DraftFormResource($document))->resolve($request),
            'series' => self::seriesOptions($type),
            'defaults' => self::defaults(Issuer::current()),
        ]);
    }

    public function update(DraftDocumentRequest $request, DocumentType $type, Document $document, UpdateDraft $updateDraft): RedirectResponse
    {
        $updateDraft($document, $request->toDraftData());

        Inertia::flash('success', 'Borrador guardado.');

        return to_route('documents.edit', self::routeParams($type, $document));
    }

    public function destroy(DocumentType $type, Document $document, DeleteDraft $deleteDraft): RedirectResponse
    {
        $deleteDraft($document);

        Inertia::flash('success', 'Borrador borrado.');

        return to_route('documents.index', self::routeParams($type));
    }

    /**
     * Valores que propone una línea nueva.
     *
     * @return array<string, string>
     */
    private static function defaults(Issuer $issuer): array
    {
        return [
            'vat_rate' => (string) config('invoice.tax.default_vat_rate'),
            'irpf_rate' => (string) $issuer->default_irpf_rate,
            'currency' => $issuer->default_currency,
        ];
    }
}
