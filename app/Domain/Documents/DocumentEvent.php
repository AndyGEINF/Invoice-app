<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada del historial de un documento: qué pasó y cuándo.
 *
 * Solo se añaden entradas; no tiene fecha de modificación.
 *
 * @property string $id
 * @property string $document_id
 * @property DocumentEventType $event
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable $created_at
 */
final class DocumentEvent extends Model
{
    use HasUuidPrimaryKey;

    /** El historial solo se añade: no hay fecha de modificación. */
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event' => DocumentEventType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
