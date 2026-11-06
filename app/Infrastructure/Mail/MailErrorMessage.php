<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Throwable;

/**
 * Traduce los errores del servidor de correo (en inglés y técnicos) a lo que
 * el usuario tiene que revisar. Se añade el original para poder diagnosticar.
 */
final class MailErrorMessage
{
    private const array AUTHENTICATION = ['535', 'authenticate', 'username and password', 'credentials'];

    private const array CONNECTION = ['connection could not be established', 'getaddrinfo', 'timed out', 'connection refused'];

    public static function for(Throwable $exception): string
    {
        $original = $exception->getMessage();
        $lower = mb_strtolower($original);

        $explanation = match (true) {
            self::mentions($lower, self::AUTHENTICATION) => 'El servidor de correo no acepta el usuario o la contraseña. Si usas Gmail, tiene que ser una contraseña de aplicación, no la de tu cuenta.',
            self::mentions($lower, self::CONNECTION) => 'No se ha podido conectar con el servidor de correo. Revisa el servidor y el puerto, y que haya conexión a internet.',
            default => null,
        };

        return $explanation === null ? $original : $explanation."\n\nDetalle: ".$original;
    }

    /** @param list<string> $needles */
    private static function mentions(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
