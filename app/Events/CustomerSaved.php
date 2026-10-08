<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un cliente se acaba de crear o modificar.
 *
 * Lo escucha la validación del número de IVA intracomunitario en VIES, que va a
 * cola y nunca bloquea el guardado.
 */
final readonly class CustomerSaved
{
    use Dispatchable;

    public function __construct(
        public string $customerId,
        public bool $taxIdChanged,
    ) {}
}
