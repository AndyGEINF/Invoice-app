<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Issuer\Issuer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo de prueba que se manda a la propia cuenta al conectarla en Ajustes. */
final class TestMail extends Mailable
{
    public function __construct(private readonly string $fromAddress) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, (string) (Issuer::current()->legalName() ?: config('app.name'))),
            subject: 'Tu correo está conectado',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test');
    }
}
