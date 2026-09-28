<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Customers\Customer;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Currency;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Presupuesto, factura o rectificativa: los tres comparten tabla, líneas,
 * impuestos y PDF (decisión D1).
 *
 * Se puede consultar como `Document` para listados mixtos; al leer de la base de
 * datos cada fila se convierte en su clase concreta ({@see Quote},
 * {@see Invoice}, {@see CreditNote}) según su tipo.
 *
 * @property string $id
 * @property DocumentType $type
 * @property string|null $series_id
 * @property int|null $fiscal_year
 * @property int|null $number
 * @property string|null $full_number
 * @property string|null $customer_id
 * @property array<string, mixed>|null $issuer_snapshot
 * @property array<string, mixed>|null $customer_snapshot
 * @property CarbonImmutable|null $issue_date
 * @property CarbonImmutable|null $operation_date
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $valid_until
 * @property Currency $currency
 * @property Percentage $global_discount_percent
 * @property Percentage $irpf_rate
 * @property Money $taxable_base
 * @property Money $vat_total
 * @property Money $surcharge_total
 * @property Money $irpf_total
 * @property Money $total
 * @property CarbonImmutable|null $paid_at
 * @property string|null $paid_note
 * @property InvoiceType|null $invoice_type
 * @property string|null $converted_from_id
 * @property string|null $rectifies_id
 * @property RectificationType|null $rectification_type
 * @property string|null $rectification_reason
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $rectified_at
 * @property string|null $pdf_path
 * @property string|null $pdf_sha256
 * @property CarbonImmutable|null $pdf_generated_at
 */
#[UseFactory(DocumentFactory::class)]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    /** Columnas que pueden cambiar en un documento fiscal ya emitido (ver trigger T028). */
    public const array MUTABLE_AFTER_ISSUE = [
        'status',
        'paid_at',
        'paid_note',
        'sent_at',
        'rectified_at',
        'pdf_path',
        'pdf_sha256',
        'pdf_generated_at',
        'internal_notes',
        'updated_at',
    ];

    /** Clase concreta de cada tipo de documento. */
    protected const array CLASS_BY_TYPE = [
        'quote' => Quote::class,
        'invoice' => Invoice::class,
        'credit_note' => CreditNote::class,
    ];

    protected $table = 'documents';

    protected $guarded = ['id'];

    protected $attributes = [
        'currency' => 'EUR',
        'global_discount_percent' => '0.00',
        'irpf_rate' => '0.00',
        'taxable_base' => 0,
        'vat_total' => 0,
        'surcharge_total' => 0,
        'irpf_total' => 0,
        'total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'fiscal_year' => 'integer',
            'number' => 'integer',
            'issuer_snapshot' => 'array',
            'customer_snapshot' => 'array',
            'issue_date' => 'immutable_date',
            'operation_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'currency' => Currency::class,
            'global_discount_percent' => PercentageCast::class,
            'irpf_rate' => PercentageCast::class,
            'taxable_base' => MoneyCast::class,
            'vat_total' => MoneyCast::class,
            'surcharge_total' => MoneyCast::class,
            'irpf_total' => MoneyCast::class,
            'total' => MoneyCast::class,
            'paid_at' => 'immutable_date',
            'invoice_type' => InvoiceType::class,
            'rectification_type' => RectificationType::class,
            'issued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'rectified_at' => 'immutable_datetime',
            'pdf_generated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Cada fila leída se convierte en su clase concreta según su tipo.
     *
     * @param  array<string, mixed>|object  $attributes
     */
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        $attributes = (array) $attributes;
        $class = static::CLASS_BY_TYPE[$attributes['type'] ?? ''] ?? static::class;

        /** @var static $model */
        $model = (new $class)->newInstance([], true);
        $model->setRawAttributes($attributes, true);
        $model->setConnection($connection ?? $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Clase concreta de un tipo, para consultar solo documentos de ese tipo.
     *
     * @return class-string<Document>
     */
    public static function classFor(DocumentType $type): string
    {
        return static::CLASS_BY_TYPE[$type->value];
    }

    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class, 'document_id')->orderBy('position');
    }

    /** @return HasMany<DocumentTax, $this> */
    public function taxes(): HasMany
    {
        return $this->hasMany(DocumentTax::class, 'document_id');
    }

    /** @return HasMany<DocumentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class, 'document_id')->orderBy('created_at');
    }

    /** @return HasMany<DocumentSend, $this> */
    public function sends(): HasMany
    {
        return $this->hasMany(DocumentSend::class, 'document_id')->orderBy('queued_at');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Series, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /** Presupuesto del que procede esta factura. */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'converted_from_id');
    }

    /** Factura que corrige esta rectificativa. */
    public function rectifies(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'rectifies_id');
    }

    /** Rectificativas emitidas sobre esta factura. */
    public function rectifications(): HasMany
    {
        return $this->hasMany(Document::class, 'rectifies_id');
    }

    public function documentType(): DocumentType
    {
        return $this->type;
    }

    public function hasNumber(): bool
    {
        return $this->number !== null;
    }

    /** Si el documento admite cambios en líneas, cliente e importes. */
    public function isEditable(): bool
    {
        return true;
    }
}
