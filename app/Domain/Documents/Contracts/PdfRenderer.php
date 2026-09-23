<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Document;

/**
 * Genera el PDF de un documento a partir de la misma plantilla que la
 * previsualización en pantalla: un diseño, no dos.
 *
 * Se ejecuta siempre desde un job de cola, nunca en la petición HTTP.
 */
interface PdfRenderer
{
    public function render(Document $document): RenderedPdf;
}
