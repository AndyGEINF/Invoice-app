<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Clave primaria UUIDv7.
 *
 * Ordenada en el tiempo, de modo que no fragmenta los índices como la v4, y sin
 * contadores a la vista en las URLs (decisión D11). Laravel genera UUIDv7 con
 * el trait HasUuids desde la versión 12.
 */
trait HasUuidPrimaryKey
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
