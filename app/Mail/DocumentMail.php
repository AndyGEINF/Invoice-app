<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Documents\DocumentSend;
use App\Domain\Issuer\Issuer;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Documento enviado al cliente con su PDF adjunto. Sale de la cuenta de correo
 * conectada en Ajustes (o la del servidor), con el nombre del emisor como
 * remitente visible, y las respuestas van al email del emisor.
 *
 * No implementa ShouldQueue: ya lo manda un job, y si se encolara otra vez ese
 * job no se enteraría de que el envío ha fallado.
 */
final class DocumentMail extends Mailable
{
    private const string PDF_MIME = 'application/pdf';

    public function __construct(
        public readonly DocumentSend $send,
        private readonly string $pdf,
        private readonly string $filename,
        private readonly string $fromAddress,
    ) {}

    public function envelope(): Envelope
    {
        $issuer = Issuer::current();
        $name = $issuer->legalName();

        return new Envelope(
            from: new Address($this->fromAddress, $name),
            replyTo: filled($issuer->email) ? [new Address((string) $issuer->email, $name)] : [],
            subject: $this->send->subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.document');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime(self::PDF_MIME)];
    }
}
