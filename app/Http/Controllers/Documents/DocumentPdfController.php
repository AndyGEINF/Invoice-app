<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Contracts\DocumentStorage;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Jobs\RenderDocumentPdfJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Sirve el PDF guardado (`?download=1` para descargarlo). Aquí nunca se genera:
 * si aún no existe se pide a la cola, y un borrador solo tiene vista previa.
 */
final class DocumentPdfController extends Controller
{
    use DocumentPageProps;

    private const string PDF_MIME = 'application/pdf';

    public function __invoke(DocumentType $type, Document $document, Request $request, DocumentStorage $storage): Response|RedirectResponse
    {
        if (! $document->hasNumber()) {
            return to_route('documents.preview', self::routeParams($type, $document));
        }

        if ($document->pdf_path === null) {
            RenderDocumentPdfJob::dispatch($document->id);
            Inertia::flash('success', 'Estamos preparando el PDF: vuelve a pulsar en unos segundos.');

            return to_route('documents.show', self::routeParams($type, $document));
        }

        $disposition = $request->boolean('download') ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE;

        return response($storage->get($document->pdf_path), Response::HTTP_OK, [
            'Content-Type' => self::PDF_MIME,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $document->pdfFilename()),
        ]);
    }
}
