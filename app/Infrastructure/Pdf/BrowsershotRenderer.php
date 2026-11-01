<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Documents\Contracts\PdfRenderer;
use App\Domain\Documents\Contracts\RenderedPdf;
use App\Domain\Documents\Document;
use App\Http\Presenters\PrintableDocument;
use Spatie\Browsershot\Browsershot;
use Spatie\TemporaryDirectory\TemporaryDirectory;

/**
 * Genera el PDF con Chromium a partir de la plantilla de la vista previa.
 *
 * El HTML se le pasa en un fichero y no con `Browsershot::html()`, que rechaza
 * cualquier texto parecido a `file://`: una línea de factura que lo citara
 * dejaría esa factura sin PDF para siempre. La plantilla escapa todo el contenido.
 */
final class BrowsershotRenderer implements PdfRenderer
{
    private const string TEMPLATE = 'pdf.document';

    private const string PAPER_FORMAT = 'A4';

    private const string HTML_FILE = 'document.html';

    public function render(Document $document): RenderedPdf
    {
        $directory = (new TemporaryDirectory)->create();

        try {
            $path = $directory->path(self::HTML_FILE);
            file_put_contents($path, view(self::TEMPLATE, ['doc' => PrintableDocument::from($document)])->render());

            return RenderedPdf::fromBytes($this->browser($path)->pdf());
        } finally {
            $directory->delete();
        }
    }

    /** Tamaño y márgenes los fija el `@page` de la plantilla. */
    private function browser(string $path): Browsershot
    {
        $browser = Browsershot::htmlFromFilePath($path)
            ->format(self::PAPER_FORMAT)
            ->setOption('preferCSSPageSize', true)
            ->showBackground()
            ->timeout((int) config('invoice.pdf.timeout'));

        if (config('invoice.pdf.no_sandbox')) {
            $browser->noSandbox();
        }

        return $this->withBinaries($browser);
    }

    private function withBinaries(Browsershot $browser): Browsershot
    {
        if (filled(config('invoice.pdf.chrome_path'))) {
            $browser->setChromePath((string) config('invoice.pdf.chrome_path'));
        }

        if (filled(config('invoice.pdf.node_binary'))) {
            $browser->setNodeBinary((string) config('invoice.pdf.node_binary'));
        }

        if (filled(config('invoice.pdf.npm_binary'))) {
            $browser->setNpmBinary((string) config('invoice.pdf.npm_binary'));
        }

        return $browser;
    }
}
