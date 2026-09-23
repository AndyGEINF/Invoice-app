<?php

declare(strict_types=1);

namespace App\Domain\Issuer;

use App\Domain\Issuer\Exceptions\IssuerNotConfigured;
use App\Domain\Shared\Address;
use App\Domain\Shared\Casts\AddressCast;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Enums\VatRegime;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\TaxId;
use Database\Factories\IssuerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Emisor único de la aplicación: la persona o empresa que factura.
 *
 * Nombre, logotipo y datos fiscales son obligatorios para emitir; el nombre de
 * empresa es opcional (un autónomo factura a su nombre). Todo lo que aparece en
 * la cabecera de la factura se congela en `issuer_snapshot` al emitir, de modo
 * que cambiar después la dirección o el logotipo no altera lo ya emitido.
 *
 * @property string $id
 * @property string|null $name
 * @property string|null $company_name
 * @property string|null $logo_path
 * @property string|null $tax_id
 * @property TaxIdType|null $tax_id_type
 * @property Address|null $address
 * @property VatRegime $vat_regime
 * @property Percentage $default_irpf_rate
 * @property string $default_currency
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $invoice_footer
 */
#[UseFactory(IssuerFactory::class)]
final class Issuer extends Model
{
    /** @use HasFactory<IssuerFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    /** Versión del formato del snapshot congelado en cada documento. */
    public const int SNAPSHOT_VERSION = 1;

    /** Claves de los datos que faltan, en el orden en que se piden en la interfaz. */
    public const string MISSING_NAME = 'name';

    public const string MISSING_LOGO = 'logo';

    public const string MISSING_TAX_ID = 'tax_id';

    public const string MISSING_ADDRESS = 'address';

    protected $table = 'issuer';

    protected $fillable = [
        'name',
        'company_name',
        'logo_path',
        'tax_id',
        'tax_id_type',
        'address',
        'vat_regime',
        'default_irpf_rate',
        'default_currency',
        'email',
        'phone',
        'website',
        'invoice_footer',
    ];

    protected $attributes = [
        'singleton' => true,
        'vat_regime' => 'general',
        'default_irpf_rate' => '0.00',
        'default_currency' => 'EUR',
    ];

    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
            'tax_id_type' => TaxIdType::class,
            'address' => AddressCast::class,
            'vat_regime' => VatRegime::class,
            'default_irpf_rate' => PercentageCast::class,
        ];
    }

    /**
     * La fila única del emisor. Si aún no existe, la crea vacía.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['singleton' => true]);
    }

    /** Nombre con el que se factura: la empresa si existe, si no la persona. */
    public function legalName(): ?string
    {
        $company = trim((string) $this->company_name);

        return $company !== '' ? $company : $this->name;
    }

    public function taxId(): ?TaxId
    {
        if ($this->tax_id === null || $this->tax_id === '') {
            return null;
        }

        return TaxId::of($this->tax_id, $this->tax_id_type);
    }

    /**
     * Datos obligatorios que faltan para poder emitir.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];

        if (trim((string) $this->name) === '') {
            $missing[] = self::MISSING_NAME;
        }

        if (trim((string) $this->logo_path) === '') {
            $missing[] = self::MISSING_LOGO;
        }

        if (! ($this->taxId()?->isValid() ?? false)) {
            $missing[] = self::MISSING_TAX_ID;
        }

        if (! ($this->address?->isComplete() ?? false)) {
            $missing[] = self::MISSING_ADDRESS;
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /** @throws IssuerNotConfigured */
    public function assertComplete(): void
    {
        $missing = $this->missing();

        if ($missing !== []) {
            throw IssuerNotConfigured::missing($missing);
        }
    }

    /**
     * Copia congelada de los datos que aparecen en la factura.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'legal_name' => $this->legalName(),
            'contact_name' => $this->name,
            'company_name' => $this->company_name,
            'logo_path' => $this->logo_path,
            'tax_id' => $this->tax_id,
            'tax_id_type' => $this->tax_id_type?->value,
            'address' => $this->address?->toArray(),
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'vat_regime' => $this->vat_regime->value,
            'invoice_footer' => $this->invoice_footer,
        ];
    }
}
