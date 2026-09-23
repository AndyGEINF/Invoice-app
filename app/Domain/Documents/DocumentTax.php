<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grupo del desglose de impuestos: base y cuota de un tipo impositivo.
 *
 * Es lo que se valida fiscalmente. Se regenera entero con cada recálculo del
 * borrador y queda inmutable al emitir (decisión D3).
 *
 * @property string $id
 * @property string $document_id
 * @property TaxType $tax_type
 * @property Percentage $rate
 * @property Money $base
 * @property Money $amount
 * @property ExemptionCode|null $exemption_code
 */
final class DocumentTax extends Model
{
    use HasUuidPrimaryKey;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'rate' => PercentageCast::class,
            'base' => MoneyCast::class,
            'amount' => MoneyCast::class,
            'exemption_code' => ExemptionCode::class,
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
