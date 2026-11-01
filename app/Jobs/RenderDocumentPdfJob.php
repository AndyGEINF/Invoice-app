<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Documents\RenderDocumentPdf;
use App\Domain\Documents\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Genera el PDF de un documento fuera de la petición HTTP. Idempotente: si el
 * documento ya tiene su PDF, no hace nada ({@see RenderDocumentPdf}).
 */
final class RenderDocumentPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Margen sobre el límite de Chromium para arrancarlo y guardar el fichero. */
    private const int TIMEOUT_MARGIN_SECONDS = 30;

    public int $tries;

    public int $backoff;

    public int $timeout;

    /** Si un worker muere a medias, el PDF se puede volver a pedir pasado este tiempo. */
    public int $uniqueFor;

    public function __construct(public readonly string $documentId)
    {
        $this->tries = (int) config('invoice.pdf.tries');
        $this->backoff = (int) config('invoice.pdf.backoff_seconds');
        $this->timeout = (int) config('invoice.pdf.timeout') + self::TIMEOUT_MARGIN_SECONDS;
        $this->uniqueFor = $this->timeout * $this->tries;
        $this->afterCommit();
    }

    /** Un solo PDF pendiente por documento. */
    public function uniqueId(): string
    {
        return $this->documentId;
    }

    public function handle(RenderDocumentPdf $render): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document !== null) {
            $render($document);
        }
    }
}
