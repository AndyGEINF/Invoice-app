<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Catalog\Product;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Casts\QuantityCast;
use App\Domain\Shared\Casts\UnitPriceCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Database\Factories\DocumentLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de un documento.
 *
 * Guarda su propia descripción, precio y tipos: el producto es solo una
 * referencia y puede desaparecer sin que la línea cambie (decisión D4).
 *
 * @property string $id
 * @property string $document_id
 * @property int $position
 * @property string|null $product_id
 * @property string $description
 * @property Quantity $quantity
 * @property string $unit
 * @property UnitPrice $unit_price
 * @property Percentage $discount_percent
 * @property Percentage $vat_rate
 * @property Percentage $surcharge_rate
 * @property bool $irpf_applies
 * @property ExemptionCode|null $exemption_code
 * @property Money $line_base
 */
#[UseFactory(DocumentLineFactory::class)]
final class DocumentLine extends Model
{
    /** @use HasFactory<DocumentLineFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $guarded = ['id'];

    protected $attributes = [
        'unit' => Product::DEFAULT_UNIT,
        'discount_percent' => '0.00',
        'surcharge_rate' => '0.00',
        'irpf_applies' => false,
        'line_base' => 0,
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => QuantityCast::class,
            'unit_price' => UnitPriceCast::class,
            'discount_percent' => PercentageCast::class,
            'vat_rate' => PercentageCast::class,
            'surcharge_rate' => PercentageCast::class,
            'irpf_applies' => 'boolean',
            'exemption_code' => ExemptionCode::class,
            'line_base' => MoneyCast::class,
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
