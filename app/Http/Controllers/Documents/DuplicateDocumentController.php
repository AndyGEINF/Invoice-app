<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\DuplicateDocument;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Crea un borrador nuevo con el contenido de un presupuesto o factura y lo abre. */
final class DuplicateDocumentController extends Controller
{
    use DocumentPageProps;

    public function __invoke(DocumentType $type, Document $document, DuplicateDocument $duplicate): RedirectResponse
    {
        $copy = $duplicate($document);

        Inertia::flash('success', sprintf(
            '%s duplicado como borrador nuevo. Revisa las fechas antes de enviarlo.',
            $document->full_number ?? $type->label(),
        ));

        return to_route('documents.show', self::routeParams($type, $copy));
    }
}
