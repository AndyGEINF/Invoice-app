<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Pages\DocumentPage;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Vista de un documento: tipo papel, editable si es borrador.
 */
final class DocumentShowController extends Controller
{
    public function __invoke(Request $request, DocumentType $type, Document $document): Response
    {
        return DocumentPage::render($request, $type, $document);
    }
}
