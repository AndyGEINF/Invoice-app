<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Issuer\Exceptions\IssuerNotConfigured;
use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * Convierte una regla de negocio incumplida en un error de validación, para que
 * el usuario vea el motivo en el formulario y nunca un 500.
 *
 * - Petición Inertia: vuelve atrás con `errors.domain`.
 * - Petición JSON: 422 con `errors.domain`.
 *
 * Solo las `DomainException` del dominio (App\Domain) son reglas de negocio; el
 * resto de excepciones siguen su curso normal.
 */
final class DomainErrors
{
    /** Clave del error en la bolsa de errores de validación. */
    public const string KEY = 'domain';

    /** Lista de datos del emisor que faltan (claves de Issuer::MISSING_*). */
    public const string ISSUER_MISSING_KEY = 'issuer_missing';

    private const string DOMAIN_NAMESPACE = 'App\\Domain\\';

    public static function isBusinessRule(DomainException $exception): bool
    {
        return str_starts_with($exception::class, self::DOMAIN_NAMESPACE);
    }

    public static function toValidation(DomainException $exception): DomainException|ValidationException
    {
        if (! self::isBusinessRule($exception)) {
            return $exception;
        }

        $messages = [self::KEY => $exception->getMessage()];

        if ($exception instanceof IssuerNotConfigured) {
            $messages[self::ISSUER_MISSING_KEY] = implode(',', $exception->missing);
        }

        return ValidationException::withMessages($messages);
    }
}
