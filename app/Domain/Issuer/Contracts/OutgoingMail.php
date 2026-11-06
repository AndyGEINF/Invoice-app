<?php

declare(strict_types=1);

namespace App\Domain\Issuer\Contracts;

/**
 * Si la aplicación puede enviar correo de verdad: con la cuenta conectada en
 * Ajustes o con un servidor configurado. Sin ella, un envío no llegaría a nadie.
 */
interface OutgoingMail
{
    public function isConnected(): bool;
}
