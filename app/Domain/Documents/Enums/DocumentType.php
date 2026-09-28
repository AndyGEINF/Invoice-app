<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

/**
 * Tipo de documento.
 *
 * Los tres viven en la misma tabla `documents` y comparten líneas, impuestos y
 * PDF; lo que cambia es su ciclo de vida (decisión D1).
 */
enum DocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Presupuesto',
            self::Invoice => 'Factura',
            self::CreditNote => 'Rectificativa',
        };
    }

    /** Segmento de URL del recurso: /quotes, /invoices, /credit-notes. */
    public function routeSegment(): string
    {
        return match ($this) {
            self::Quote => 'quotes',
            self::Invoice => 'invoices',
            self::CreditNote => 'credit-notes',
        };
    }

    /** Inverso de {@see routeSegment()}: null si el segmento no es un tipo. */
    public static function tryFromRouteSegment(string $segment): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->routeSegment() === $segment) {
                return $type;
            }
        }

        return null;
    }

    /** Los documentos fiscales son inmutables una vez emitidos. */
    public function isFiscal(): bool
    {
        return $this !== self::Quote;
    }
}
