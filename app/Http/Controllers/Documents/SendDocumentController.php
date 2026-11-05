<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\SendDocumentByEmail;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Requests\SendDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Envío por email (`POST /{type}/{id}/email`). No usa `/send` porque en los
 * presupuestos esa ruta es la transición "marcar como enviado".
 */
final class SendDocumentController extends Controller
{
    use DocumentPageProps;

    public function __invoke(DocumentType $type, Document $document, SendDocumentRequest $request, SendDocumentByEmail $send): RedirectResponse
    {
        $send($document, $request->toSendData());

        Inertia::flash('success', sprintf('%s %s en camino: verás el resultado en Envíos.', $type->label(), $document->full_number));

        return to_route('documents.show', self::routeParams($type, $document));
    }
}
