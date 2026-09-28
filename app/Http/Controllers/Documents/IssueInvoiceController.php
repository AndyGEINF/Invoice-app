<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\IssueInvoice;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Requests\IssueInvoiceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Emite una factura o rectificativa y vuelve a su detalle con el número
 * asignado. Los avisos (p. ej. simplificada por encima del límite) no impiden
 * emitir: llegan al usuario como `flash.warnings`.
 */
final class IssueInvoiceController extends Controller
{
    use DocumentPageProps;

    public function __invoke(IssueInvoiceRequest $request, DocumentType $type, Document $document, IssueInvoice $issueInvoice): RedirectResponse
    {
        $result = $issueInvoice(
            $document,
            $request->seriesId(),
            $request->issueDate(),
            $request->operationDate(),
        );

        Inertia::flash([
            'success' => sprintf('%s %s emitida.', $type->label(), $result->invoice->full_number),
            'warnings' => $result->warningMessages(),
        ]);

        return to_route('documents.show', self::routeParams($type, $result->invoice));
    }
}
