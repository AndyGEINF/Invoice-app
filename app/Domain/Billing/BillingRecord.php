<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Billing\Exceptions\BillingRecordIsAppendOnly;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de facturación encadenado (VeriFactu).
 *
 * Append-only: solo se crean registros. Cambiar o borrar uno rompería la cadena
 * de huellas, así que el modelo lo impide y la base de datos también (trigger de
 * T027). En la fase 1 no se escribe ninguno; la tabla existe para que la fase
 * fiscal no tenga que tocar facturas ya emitidas.
 *
 * @property string $id
 * @property string $document_id
 * @property string $record_type
 * @property InvoiceType $invoice_type
 * @property string $issuer_tax_id
 * @property string $series_number
 * @property CarbonImmutable $issue_date
 * @property Money $total_amount
 * @property Money $tax_amount
 * @property string|null $previous_record_id
 * @property string|null $previous_hash
 * @property string $hash
 * @property CarbonImmutable $hashed_at
 * @property string $software_id
 * @property string $software_version
 * @property string $installation_number
 * @property string|null $qr_payload
 * @property string|null $xml_payload
 * @property string|null $aeat_status
 * @property string|null $aeat_csv
 * @property array<string, mixed>|null $aeat_response
 * @property string $idempotency_key
 */
final class BillingRecord extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'issue_date' => 'immutable_date',
            'total_amount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'hashed_at' => 'immutable_datetime',
            'aeat_response' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw BillingRecordIsAppendOnly::forOperation('modificarlos');
        });

        self::deleting(static function (): never {
            throw BillingRecordIsAppendOnly::forOperation('borrarlos');
        });
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<self, $this> */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_record_id');
    }
}
