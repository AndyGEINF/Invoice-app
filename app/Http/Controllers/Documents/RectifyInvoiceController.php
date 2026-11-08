<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\CreateRectification;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Requests\RectifyInvoiceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Crea una rectificativa en borrador de una factura emitida y la abre. */
final class RectifyInvoiceController extends Controller
{
    use DocumentPageProps;

    public function __invoke(DocumentType $type, Document $document, RectifyInvoiceRequest $request, CreateRectification $rectify): RedirectResponse
    {
        /** @var Invoice $document */
        $creditNote = $rectify($document, $request->rectificationType(), $request->reason());

        Inertia::flash('success', sprintf(
            'Rectificativa de la factura %s creada en borrador. Revísala y emítela cuando esté lista.',
            $document->full_number,
        ));

        return to_route('documents.show', self::routeParams(DocumentType::CreditNote, $creditNote));
    }
}
