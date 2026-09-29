<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PrintableDocument;
use Illuminate\Contracts\View\View;

/**
 * Vista previa HTML con la misma plantilla que el PDF: lo que se ve aquí es lo
 * que recibirá el cliente. Un borrador sale marcado como tal.
 */
final class DocumentPreviewController extends Controller
{
    public function __invoke(DocumentType $type, Document $document): View
    {
        return view('pdf.document', ['doc' => PrintableDocument::from($document)]);
    }
}
