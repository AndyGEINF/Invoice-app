<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Shared\Address;
use App\Domain\Shared\Casts\AddressCast;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\TaxId;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Destinatario de presupuestos y facturas.
 *
 * Sus datos se copian al documento al emitirlo: cambiar después la dirección o
 * el nombre no altera lo ya emitido. Un cliente con documentos emitidos se
 * archiva, nunca se borra.
 *
 * @property string $id
 * @property CustomerKind $kind
 * @property string $legal_name
 * @property string|null $trade_name
 * @property string|null $tax_id
 * @property TaxIdType|null $tax_id_type
 * @property CarbonImmutable|null $vies_validated_at
 * @property Address $billing_address
 * @property string|null $email
 * @property int $payment_terms_days
 * @property bool $irpf_applies
 * @property bool $surcharge_applies
 * @property Percentage|null $default_vat_rate
 * @property string|null $notes
 * @property CarbonImmutable|null $archived_at
 */
#[UseFactory(CustomerFactory::class)]
final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    public const int SNAPSHOT_VERSION = 1;

    /** Plazo de pago si no se indica otro. */
    public const int DEFAULT_PAYMENT_TERMS_DAYS = 30;

    protected $fillable = [
        'kind',
        'legal_name',
        'trade_name',
        'tax_id',
        'tax_id_type',
        'vies_validated_at',
        'billing_address',
        'email',
        'payment_terms_days',
        'irpf_applies',
        'surcharge_applies',
        'default_vat_rate',
        'notes',
    ];

    protected $attributes = [
        'payment_terms_days' => self::DEFAULT_PAYMENT_TERMS_DAYS,
        'irpf_applies' => false,
        'surcharge_applies' => false,
    ];

    protected function casts(): array
    {
        return [
            'kind' => CustomerKind::class,
            'tax_id_type' => TaxIdType::class,
            'vies_validated_at' => 'immutable_datetime',
            'billing_address' => AddressCast::class,
            'payment_terms_days' => 'integer',
            'irpf_applies' => 'boolean',
            'surcharge_applies' => 'boolean',
            'default_vat_rate' => PercentageCast::class,
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function defaultContact(): ?Contact
    {
        return $this->contacts()->where('is_default', true)->first()
            ?? $this->contacts()->oldest()->first();
    }

    /** Email al que se envían los documentos por defecto. */
    public function billingEmail(): ?string
    {
        return $this->defaultContact()?->email ?? $this->email;
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->forceFill(['archived_at' => CarbonImmutable::now()])->save();
    }

    public function restore(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }

    public function isBusiness(): bool
    {
        return $this->kind === CustomerKind::Business;
    }

    public function taxId(): ?TaxId
    {
        if ($this->tax_id === null || $this->tax_id === '') {
            return null;
        }

        return TaxId::of($this->tax_id, $this->tax_id_type);
    }

    public function hasTaxId(): bool
    {
        return $this->taxId() !== null;
    }

    /** La retención de IRPF solo se practica a empresas y profesionales. */
    public function appliesIrpf(): bool
    {
        return $this->irpf_applies && $this->isBusiness();
    }

    /** Nombre que se muestra en listados: el comercial si existe. */
    public function displayName(): string
    {
        $trade = trim((string) $this->trade_name);

        return $trade !== '' ? $trade : $this->legal_name;
    }

    /**
     * Copia congelada de los datos del cliente que aparecen en el documento.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'kind' => $this->kind->value,
            'tax_id' => $this->tax_id,
            'tax_id_type' => $this->tax_id_type?->value,
            'address' => $this->billing_address->toArray(),
            'email' => $this->billingEmail(),
            'irpf_applies' => $this->appliesIrpf(),
            'surcharge_applies' => $this->surcharge_applies,
            'payment_terms_days' => $this->payment_terms_days,
        ];
    }
}
