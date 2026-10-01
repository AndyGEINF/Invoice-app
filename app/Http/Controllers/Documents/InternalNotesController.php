<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\UpdateInternalNotes;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DraftDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Guarda las notas internas, con independencia del borrador: no pisa las líneas
 * que se estén editando y funciona también en documentos emitidos.
 */
final class InternalNotesController extends Controller
{
    public function __invoke(Request $request, DocumentType $type, Document $document, UpdateInternalNotes $updateNotes): RedirectResponse
    {
        $validated = $request->validate(
            ['internal_notes' => ['nullable', 'string', 'max:'.DraftDocumentRequest::MAX_NOTES_LENGTH]],
            attributes: ['internal_notes' => 'notas internas'],
        );

        $updateNotes($document, $validated['internal_notes'] ?? null);

        Inertia::flash('success', 'Notas guardadas.');

        return back();
    }
}
