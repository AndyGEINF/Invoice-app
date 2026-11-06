<?php

declare(strict_types=1);

namespace App\Domain\Issuer\Enums;

/**
 * Servicio de correo con el que se envían los documentos. Gmail y Brevo traen
 * su servidor ya puesto; "otro" deja escribir servidor y puerto.
 */
enum MailProvider: string
{
    case Gmail = 'gmail';
    case Brevo = 'brevo';
    case Custom = 'custom';

    /** Puerto SMTP con STARTTLS, el habitual de los servicios de envío. */
    private const int SUBMISSION_PORT = 587;

    public function label(): string
    {
        return match ($this) {
            self::Gmail => 'Gmail',
            self::Brevo => 'Brevo',
            self::Custom => 'Otro servidor',
        };
    }

    /** Servidor SMTP del servicio; null si lo escribe el usuario. */
    public function host(): ?string
    {
        return match ($this) {
            self::Gmail => 'smtp.gmail.com',
            self::Brevo => 'smtp-relay.brevo.com',
            self::Custom => null,
        };
    }

    public function port(): ?int
    {
        return $this === self::Custom ? null : self::SUBMISSION_PORT;
    }

    /** En Gmail el usuario es el propio email; en el resto es un dato aparte. */
    public function usesAddressAsUsername(): bool
    {
        return $this === self::Gmail;
    }
}
