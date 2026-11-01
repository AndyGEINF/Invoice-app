<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Contracts\DocumentStorage;
use App\Domain\Documents\Contracts\PdfRenderer;
use App\Domain\Documents\Document;
use App\Domain\Shared\Contracts\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Genera y guarda el PDF de un documento con número, si aún no lo tiene.
 *
 * El de una factura emitida se genera una sola vez y se conserva: descargarlo
 * años después da el mismo fichero. Un borrador no tiene PDF, solo vista previa.
 * Un presupuesto sí se puede editar: al cambiar pierde el suyo y se genera otro.
 *
 * Se renderiza con la fila bloqueada para que dos jobs no generen dos ficheros
 * distintos y una edición no quede pisada por un PDF con los datos anteriores.
 */
final readonly class RenderDocumentPdf
{
    private const string PDF_EXTENSION = '.pdf';

    public function __construct(
        private PdfRenderer $renderer,
        private DocumentStorage $storage,
        private Clock $clock,
    ) {}

    public function __invoke(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $document = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());

            if ($document->pdf_sha256 !== null || ! $document->hasNumber()) {
                return $document;
            }

            $pdf = $this->renderer->render($document);
            $path = $document->id.self::PDF_EXTENSION;

            $this->storage->put($path, $pdf->bytes);

            $document->forceFill([
                'pdf_path' => $path,
                'pdf_sha256' => $pdf->sha256,
                'pdf_generated_at' => $this->clock->now(),
            ])->save();

            return $document;
        });
    }
}
