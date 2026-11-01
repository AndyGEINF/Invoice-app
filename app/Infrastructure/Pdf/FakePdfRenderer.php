<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Documents\Contracts\PdfRenderer;
use App\Domain\Documents\Contracts\RenderedPdf;
use App\Domain\Documents\Document;

/**
 * PDF de mentira, sin Chromium, para los tests. Cuenta cuántas veces se le pide
 * para comprobar que un documento emitido no se vuelve a generar.
 */
final class FakePdfRenderer implements PdfRenderer
{
    public int $renders = 0;

    public function render(Document $document): RenderedPdf
    {
        $this->renders++;

        return RenderedPdf::fromBytes('%PDF-fake '.$document->id);
    }
}
