<?php

declare(strict_types=1);

namespace App\Domain\Documents\Concerns;

use App\Domain\Documents\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hace que una subclase de Document solo vea y cree filas de su tipo.
 */
trait IsTypedDocument
{
    abstract public static function fixedType(): DocumentType;

    protected static function bootIsTypedDocument(): void
    {
        static::addGlobalScope('document_type', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('type'), static::fixedType()->value);
        });

        static::creating(function (self $document): void {
            $document->type = static::fixedType();
        });
    }

    public function initializeIsTypedDocument(): void
    {
        $this->attributes['type'] ??= static::fixedType()->value;
    }
}
