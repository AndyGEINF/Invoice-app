<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\ConvertQuoteToInvoice;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Quote;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Convierte un presupuesto aceptado en factura borrador y abre la factura. */
final class ConvertQuoteController extends Controller
{
    use DocumentPageProps;

    public function __invoke(DocumentType $type, Document $document, ConvertQuoteToInvoice $convert): RedirectResponse
    {
        /** @var Quote $document */
        $invoice = $convert($document);

        Inertia::flash('success', sprintf(
            'Factura creada a partir del presupuesto %s. Revísala y emítela cuando esté lista.',
            $document->full_number,
        ));

        return to_route('documents.show', self::routeParams(DocumentType::Invoice, $invoice));
    }
}
