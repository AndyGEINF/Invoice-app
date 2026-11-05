<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Application\Documents\TransitionQuote;
use App\Application\Documents\UpdateDraft;
use App\Domain\Customers\Customer;
use App\Domain\Documents\Contracts\PdfRenderer;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Pdf\FakePdfRenderer;
use App\Infrastructure\Time\FrozenClock;
use App\Jobs\RenderDocumentPdfJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const PDF_NOW = '2026-10-20 10:00:00';

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock(PDF_NOW));
    Series::factory()->invoices()->create();
    Series::factory()->quotes()->create();
    configuredIssuer();
});

/** @param array<string, mixed> $form */
function pdfDraft(DocumentType $type = DocumentType::Invoice, array $form = []): Document
{
    return app(CreateDraft::class)($type, DraftData::fromArray([
        'customer_id' => Customer::factory()->create()->id,
        'lines' => [['position' => 1, 'description' => 'Diseño de logotipo', 'quantity' => '1', 'unit_price' => '500', 'vat_rate' => '21']],
        ...$form,
    ]));
}

function issuedWithPdf(): Invoice
{
    $draft = pdfDraft();
    app(IssueInvoice::class)($draft);

    /** @var Invoice */
    return $draft->refresh();
}

function fakePdf(): FakePdfRenderer
{
    /** @var FakePdfRenderer */
    return app(PdfRenderer::class);
}

describe('al emitir', function () {
    it('encola el PDF de la factura', function () {
        Queue::fake();
        $draft = pdfDraft();

        app(IssueInvoice::class)($draft);

        Queue::assertPushed(RenderDocumentPdfJob::class, fn (RenderDocumentPdfJob $job): bool => $job->documentId === $draft->id);
    });

    it('el job guarda el PDF con su huella y su fecha', function () {
        $invoice = issuedWithPdf();

        expect($invoice->pdf_path)->toBe($invoice->id.'.pdf')
            ->and($invoice->pdf_sha256)->toBe(hash('sha256', '%PDF-fake '.$invoice->id))
            ->and($invoice->pdf_generated_at?->toDateTimeString())->toBe(PDF_NOW)
            ->and(fakePdf()->renders)->toBe(1);
        Storage::disk(config('invoice.storage.documents_disk'))->assertExists($invoice->pdf_path);
    });

    it('una segunda ejecución no lo vuelve a generar aunque el emisor haya cambiado', function () {
        $invoice = issuedWithPdf();
        Issuer::current()->update(['company_name' => 'Otra Empresa SL']);

        RenderDocumentPdfJob::dispatchSync($invoice->id);

        expect(fakePdf()->renders)->toBe(1)
            ->and($invoice->refresh()->pdf_sha256)->toBe(hash('sha256', '%PDF-fake '.$invoice->id));
    });
});

describe('GET /pdf', function () {
    it('devuelve el fichero guardado para verlo en el navegador', function () {
        $invoice = issuedWithPdf();

        $response = $this->get("/invoices/{$invoice->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        expect($response->getContent())->toBe('%PDF-fake '.$invoice->id)
            ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename=Factura-'.$invoice->full_number.'.pdf');
    });

    it('con ?download=1 lo descarga', function () {
        $invoice = issuedWithPdf();

        $this->get("/invoices/{$invoice->id}/pdf?download=1")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=Factura-'.$invoice->full_number.'.pdf');
    });

    it('si aún no existe no lo genera en la petición: lo pide a la cola y vuelve al documento', function () {
        Queue::fake();
        $invoice = Invoice::factory()->issued()->create();

        $this->get("/invoices/{$invoice->id}/pdf")->assertRedirect("/invoices/{$invoice->id}");

        Queue::assertPushed(RenderDocumentPdfJob::class);
        expect(fakePdf()->renders)->toBe(0);
    });

    it('un borrador no tiene PDF: lleva a la vista previa', function () {
        $draft = pdfDraft();

        $this->get("/invoices/{$draft->id}/pdf")->assertRedirect("/invoices/{$draft->id}/preview");

        expect(fakePdf()->renders)->toBe(0)->and($draft->refresh()->pdf_path)->toBeNull();
    });
});

describe('presupuestos', function () {
    it('al marcarlo como enviado se genera su PDF', function () {
        /** @var Quote $quote */
        $quote = pdfDraft(DocumentType::Quote);

        app(TransitionQuote::class)($quote, QuoteStatus::Sent);

        expect($quote->refresh()->pdf_sha256)->not->toBeNull();
        $this->get("/quotes/{$quote->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });

    it('al editarlo pierde el PDF anterior y se genera otro', function () {
        /** @var Quote $quote */
        $quote = pdfDraft(DocumentType::Quote);
        app(TransitionQuote::class)($quote, QuoteStatus::Sent);

        app(UpdateDraft::class)($quote->refresh(), DraftData::fromArray([
            'customer_id' => $quote->customer_id,
            'lines' => [['position' => 1, 'description' => 'Diseño de logotipo y tarjetas', 'quantity' => '1', 'unit_price' => '650', 'vat_rate' => '21']],
        ]));

        expect(fakePdf()->renders)->toBe(2)
            ->and($quote->refresh()->pdf_sha256)->not->toBeNull();
    });

    it('un presupuesto en borrador no tiene PDF', function () {
        $quote = pdfDraft(DocumentType::Quote);

        $this->get("/quotes/{$quote->id}/pdf")->assertRedirect("/quotes/{$quote->id}/preview");
    });
});
