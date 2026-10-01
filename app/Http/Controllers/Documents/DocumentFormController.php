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
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Pages\DocumentPage;
use App\Http\Requests\DraftDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta, edición y borrado de borradores. Se editan sobre el propio documento
 * (página `documents/show`); cada guardado recalcula en el servidor y vuelve
 * al documento con el desglose nuevo: el frontend nunca calcula.
 */
final class DocumentFormController extends Controller
{
    use DocumentPageProps;

    /**
     * Documento nuevo sin guardar: no se crea nada hasta el primer guardado,
     * así abrir y cerrar no deja borradores vacíos.
     */
    public function create(Request $request, DocumentType $type): Response
    {
        $customerId = $request->query('customer_id');
        $customerId = is_string($customerId) && Str::isUuid($customerId) && Customer::query()->whereKey($customerId)->exists()
            ? $customerId
            : null;

        return DocumentPage::render($request, $type, null, $customerId);
    }

    public function store(DraftDocumentRequest $request, DocumentType $type, CreateDraft $createDraft): RedirectResponse
    {
        $document = $createDraft($type, $request->toDraftData());

        Inertia::flash('success', 'Borrador guardado.');

        return to_route('documents.show', self::routeParams($type, $document));
    }

    /** La edición vive en la vista del documento; un documento emitido no se edita. */
    public function edit(DocumentType $type, Document $document): RedirectResponse
    {
        if (! $document->isEditable()) {
            throw DocumentIsImmutable::notEditable($document);
        }

        return to_route('documents.show', self::routeParams($type, $document));
    }

    public function update(DraftDocumentRequest $request, DocumentType $type, Document $document, UpdateDraft $updateDraft): RedirectResponse
    {
        $updateDraft($document, $request->toDraftData());

        Inertia::flash('success', 'Borrador guardado.');

        return to_route('documents.show', self::routeParams($type, $document));
    }

    public function destroy(DocumentType $type, Document $document, DeleteDraft $deleteDraft): RedirectResponse
    {
        $deleteDraft($document);

        Inertia::flash('success', 'Borrador borrado.');

        return to_route('documents.index', self::routeParams($type));
    }
}
