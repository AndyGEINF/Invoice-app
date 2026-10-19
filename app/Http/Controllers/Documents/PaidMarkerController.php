<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\MarkAsPaid;
use App\Application\Documents\UnmarkPaid;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Requests\MarkPaidRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Marcador de cobro de facturas y rectificativas emitidas
 * (`POST` y `DELETE /{invoices|credit-notes}/{id}/paid`). Las reglas las aplica
 * {@see MarkAsPaid}; un error de negocio vuelve al documento como `errors.domain`.
 */
final class PaidMarkerController extends Controller
{
    use DocumentPageProps;

    public function store(DocumentType $type, Document $document, MarkPaidRequest $request, MarkAsPaid $markAsPaid): RedirectResponse
    {
        /** @var Invoice|CreditNote $document */
        $document = $markAsPaid($document, $request->paidOn(), $request->note());

        Inertia::flash('success', sprintf('%s %s marcada como cobrada.', $type->label(), $document->full_number));

        return to_route('documents.show', self::routeParams($type, $document));
    }

    public function destroy(DocumentType $type, Document $document, UnmarkPaid $unmarkPaid): RedirectResponse
    {
        /** @var Invoice|CreditNote $document */
        $document = $unmarkPaid($document);

        Inertia::flash('success', sprintf('%s %s ya no está marcada como cobrada.', $type->label(), $document->full_number));

        return to_route('documents.show', self::routeParams($type, $document));
    }
}
