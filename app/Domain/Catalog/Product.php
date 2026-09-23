<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Casts\UnitPriceCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\UnitPrice;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto o servicio del catálogo.
 *
 * Es solo un punto de partida: al añadirlo a un documento se copian su
 * descripción, precio y tipo de IVA, y cambiar el catálogo después no altera
 * las líneas existentes (decisión D4).
 *
 * @property string $id
 * @property string|null $sku
 * @property ProductType $type
 * @property string $name
 * @property string|null $description
 * @property UnitPrice $unit_price
 * @property string $unit
 * @property Percentage $vat_rate
 * @property ExemptionCode|null $exemption_code
 * @property bool $irpf_applicable
 * @property CarbonImmutable|null $archived_at
 */
#[UseFactory(ProductFactory::class)]
final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    public const string DEFAULT_UNIT = 'ud';

    protected $fillable = [
        'sku',
        'type',
        'name',
        'description',
        'unit_price',
        'unit',
        'vat_rate',
        'exemption_code',
        'irpf_applicable',
    ];

    protected $attributes = [
        'unit' => self::DEFAULT_UNIT,
        'irpf_applicable' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'unit_price' => UnitPriceCast::class,
            'vat_rate' => PercentageCast::class,
            'exemption_code' => ExemptionCode::class,
            'irpf_applicable' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
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

    /** Texto que se copia a la línea del documento. */
    public function lineDescription(): string
    {
        $description = trim((string) $this->description);

        return $description !== '' ? $this->name."\n".$description : $this->name;
    }
}
