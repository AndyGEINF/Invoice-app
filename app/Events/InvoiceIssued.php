<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una factura o rectificativa acaba de recibir su número.
 *
 * Se lanza dentro de la transacción de emisión: los listeners síncronos (el
 * historial) quedan en la misma transacción, y los que van a cola (PDF, más
 * adelante) deben esperar al commit.
 */
final readonly class InvoiceIssued
{
    use Dispatchable;

    /** @param array<string, mixed> $details Datos que se guardan en el historial. */
    public function __construct(
        public string $documentId,
        public array $details = [],
    ) {}
}
