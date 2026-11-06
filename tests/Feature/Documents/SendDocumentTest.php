<?php

declare(strict_types=1);

use App\Domain\Customers\Customer;
use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Enums\SendStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Issuer\Enums\MailProvider;
use App\Domain\Issuer\Issuer;
use App\Domain\Issuer\MailSettings;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use App\Jobs\SendDocumentEmailJob;
use App\Mail\DocumentMail;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;

const SEND_NOW = '2026-10-20 10:00:00';

const SEND_TO = 'cliente@ejemplo.test';

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock(SEND_NOW));
    $this->travelTo(SEND_NOW);
    configuredIssuer();
    // Como si el servidor tuviera SMTP: sin correo conectado no se deja enviar.
    config(['mail.default' => 'smtp']);
});

/** @return array<string, mixed> */
function sendForm(array $overrides = []): array
{
    return [
        'to' => [SEND_TO],
        'subject' => 'Tu factura',
        'body' => "Hola:\n\nTe adjunto la factura.",
        ...$overrides,
    ];
}

function sendableInvoice(): Invoice
{
    return Invoice::factory()->issued()->create(['customer_id' => Customer::factory()->create()->id]);
}

describe('qué se puede enviar', function () {
    it('un borrador no: 422 y no se registra nada', function () {
        $draft = Invoice::factory()->draft()->withTotals()->create();

        $this->postJson("/invoices/{$draft->id}/email", sendForm())->assertUnprocessable()->assertJsonValidationErrors('domain');

        expect(DocumentSend::query()->count())->toBe(0);
    });

    it('un presupuesto en borrador tampoco', function () {
        $quote = Quote::factory()->create();

        $this->postJson("/quotes/{$quote->id}/email", sendForm())->assertUnprocessable()->assertJsonValidationErrors('domain');
    });

    it('un presupuesto enviado sí', function () {
        Mail::fake();
        $quote = Quote::factory()->sent()->create(['number' => 1, 'full_number' => 'P2026-0001']);

        $this->post("/quotes/{$quote->id}/email", sendForm())->assertRedirect("/quotes/{$quote->id}");

        Mail::assertSent(DocumentMail::class);
        expect($quote->refresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('sin correo conectado no se envía: 422 y no se registra nada', function () {
        config(['mail.default' => 'log']);
        $invoice = sendableInvoice();

        $this->postJson("/invoices/{$invoice->id}/email", sendForm())->assertUnprocessable()->assertJsonValidationErrors('domain');

        expect(DocumentSend::query()->count())->toBe(0);
    });

    it('exige al menos un destinatario válido, asunto y mensaje', function () {
        $invoice = sendableInvoice();

        $this->postJson("/invoices/{$invoice->id}/email", sendForm(['to' => ['no-es-un-email'], 'subject' => '', 'body' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to.0', 'subject', 'body']);

        $this->postJson("/invoices/{$invoice->id}/email", sendForm(['to' => []]))->assertJsonValidationErrors('to');
    });
});

describe('envío de una factura emitida', function () {
    it('registra el envío en cola y encola el job', function () {
        Queue::fake();
        $invoice = sendableInvoice();

        $this->post("/invoices/{$invoice->id}/email", sendForm(['cc' => ['gestoria@ejemplo.test']]))->assertRedirect("/invoices/{$invoice->id}");

        $send = DocumentSend::query()->sole();
        expect($send->status)->toBe(SendStatus::Queued)
            ->and($send->to)->toBe([SEND_TO])
            ->and($send->cc)->toBe(['gestoria@ejemplo.test'])
            ->and($invoice->refresh()->status)->toBe(DocumentStatus::Issued);
        Queue::assertPushed(SendDocumentEmailJob::class, fn (SendDocumentEmailJob $job): bool => $job->sendId === $send->id);
    });

    it('manda el PDF adjunto desde el emisor y la deja enviada', function () {
        Mail::fake();
        $invoice = sendableInvoice();
        $issuer = Issuer::current();

        $this->post("/invoices/{$invoice->id}/email", sendForm());

        $invoice->refresh();
        Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) use ($invoice, $issuer): bool {
            expect($mail->hasTo(SEND_TO))->toBeTrue('destinatario')
                ->and($mail->hasSubject('Tu factura'))->toBeTrue('asunto')
                ->and($mail->hasFrom((string) config('mail.from.address'), $issuer->legalName()))->toBeTrue('remitente')
                ->and($mail->hasReplyTo((string) $issuer->email, $issuer->legalName()))->toBeTrue('responder a')
                ->and($mail->hasAttachment(Attachment::fromData(fn (): string => '%PDF-fake '.$invoice->id, $invoice->pdfFilename())->withMime('application/pdf')))->toBeTrue('adjunto');

            return true;
        });

        $send = DocumentSend::query()->sole();
        expect($send->status)->toBe(SendStatus::Sent)
            ->and($send->sent_at?->toDateTimeString())->toBe(SEND_NOW)
            ->and($invoice->status)->toBe(DocumentStatus::Sent)
            ->and($invoice->sent_at?->toDateTimeString())->toBe(SEND_NOW)
            ->and($invoice->events()->where('event', DocumentEventType::Sent)->count())->toBe(1);
    });

    it('reenviar crea otro envío y no cambia el estado ni la fecha del primero', function () {
        Mail::fake();
        $invoice = sendableInvoice();
        $this->post("/invoices/{$invoice->id}/email", sendForm());

        $this->travel(1)->days();
        $this->post("/invoices/{$invoice->id}/email", sendForm());

        $invoice->refresh();
        expect(DocumentSend::query()->where('status', SendStatus::Sent)->count())->toBe(2)
            ->and($invoice->status)->toBe(DocumentStatus::Sent)
            ->and($invoice->sent_at?->toDateTimeString())->toBe(SEND_NOW);
        Mail::assertSentCount(2);
    });

    it('un envío ya hecho no se repite si el job vuelve a ejecutarse', function () {
        Mail::fake();
        $invoice = sendableInvoice();
        $this->post("/invoices/{$invoice->id}/email", sendForm());

        SendDocumentEmailJob::dispatchSync(DocumentSend::query()->sole()->id);

        Mail::assertSentCount(1);
    });
});

describe('fallo del correo', function () {
    it('deja el envío fallido con el error, apunta el fallo y no cambia el estado', function () {
        $invoice = sendableInvoice();
        $send = $invoice->sends()->create([...sendForm(), 'queued_at' => now()]);
        // Cuenta conectada a un puerto donde no escucha nadie: el servidor rechaza la conexión.
        MailSettings::current()->fill([
            'provider' => MailProvider::Custom,
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => 'facturas@demo.test',
            'password' => 'secreta',
            'from_address' => 'facturas@demo.test',
        ])->save();

        expect(fn () => SendDocumentEmailJob::dispatchSync($send->id))->toThrow(TransportException::class);

        $send->refresh();
        $invoice->refresh();
        expect($send->status)->toBe(SendStatus::Failed)
            ->and($send->error)->toStartWith('No se ha podido conectar con el servidor de correo.')
            ->and($invoice->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->sent_at)->toBeNull()
            ->and($invoice->events()->where('event', DocumentEventType::SendFailed)->count())->toBe(1);
    });
});
