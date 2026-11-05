<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Un envío ha agotado sus reintentos sin que el proveedor lo aceptara. */
final readonly class DocumentEmailFailed
{
    use Dispatchable;

    public function __construct(public string $sendId) {}
}
