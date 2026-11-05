<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** El proveedor de correo ha aceptado un envío. */
final readonly class DocumentEmailSent
{
    use Dispatchable;

    public function __construct(public string $sendId) {}
}
