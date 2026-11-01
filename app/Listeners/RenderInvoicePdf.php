<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoiceIssued;
use App\Jobs\RenderDocumentPdfJob;

/**
 * Al emitir una factura o rectificativa, encola su PDF definitivo. El job sale
 * tras el commit: si la emisión se deshace, no se genera nada.
 */
final readonly class RenderInvoicePdf
{
    public function handle(InvoiceIssued $event): void
    {
        RenderDocumentPdfJob::dispatch($event->documentId);
    }
}
