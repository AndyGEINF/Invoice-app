<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Enums\SendStatus;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intento de envío de un documento por email.
 *
 * Cada envío es una fila: un fallo queda registrado y se puede reintentar sin
 * cambiar el estado del documento.
 *
 * @property string $id
 * @property string $document_id
 * @property list<string> $to
 * @property list<string>|null $cc
 * @property string $subject
 * @property string $body
 * @property SendStatus $status
 * @property string|null $error
 * @property string|null $provider_message_id
 * @property CarbonImmutable $queued_at
 * @property CarbonImmutable|null $sent_at
 */
final class DocumentSend extends Model
{
    use HasUuidPrimaryKey;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'status' => SendStatus::class,
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
