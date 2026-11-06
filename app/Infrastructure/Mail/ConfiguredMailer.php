<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Issuer\MailSettings;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * El mailer con el que salen los correos: la cuenta conectada en Ajustes o, si
 * no hay ninguna, la del servidor (`MAIL_*`).
 *
 * Se registra como un mailer con nombre en lugar de usar `Mail::build()` para
 * que `Mail::fake()` lo intercepte en los tests. Se rehace en cada envío: un
 * worker que lleva horas en marcha no debe seguir con una cuenta ya cambiada.
 */
final class ConfiguredMailer
{
    private const string MAILER = 'issuer';

    public function mailer(): Mailer
    {
        $settings = MailSettings::current();

        if (! $settings->isConfigured()) {
            return Mail::mailer();
        }

        config(['mail.mailers.'.self::MAILER => $settings->transportConfig()]);
        Mail::purge(self::MAILER);

        return Mail::mailer(self::MAILER);
    }

    /** Dirección del remitente: la de la cuenta conectada o la del servidor. */
    public function fromAddress(): string
    {
        $settings = MailSettings::current();

        return $settings->isConfigured() ? (string) $settings->from_address : (string) config('mail.from.address');
    }

    /** Si los correos salen de verdad: con la cuenta de Ajustes o un servidor configurado. */
    public function isConnected(): bool
    {
        return MailSettings::current()->isConfigured()
            || ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
