<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Exceptions\DocumentIsImmutable;
use App\Domain\Issuer\Issuer;
use App\Domain\Tax\LineInput;
use App\Domain\Tax\TaxBreakdown;
use App\Domain\Tax\TaxCalculator;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxGroup;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula un borrador: desglose de impuestos, base de cada línea y totales.
 *
 * El desglose se regenera entero en cada cálculo (decisión D3) y todo se
 * escribe en una transacción, así que el documento nunca queda a medias.
 */
final readonly class RecalculateDocument
{
    public function __construct(private TaxCalculator $calculator) {}

    public function __invoke(Document $document): TaxBreakdown
    {
        if (! $document->isEditable()) {
            throw $document->type === DocumentType::Quote
                ? DocumentIsImmutable::quoteConverted($document->full_number ?? $document->id)
                : DocumentIsImmutable::cannotChangeLines($document->full_number ?? $document->id);
        }

        return DB::transaction(function () use ($document): TaxBreakdown {
            /** @var list<DocumentLine> $lines */
            $lines = $document->lines()->get()->all();

            $breakdown = $this->calculator->calculate(
                array_map(self::toLineInput(...), $lines),
                $this->contextFor($document),
            );

            foreach ($lines as $index => $line) {
                $line->line_base = $breakdown->lineBases[$index];
                $line->save();
            }

            $document->taxes()->delete();
            $document->taxes()->createMany(array_map(
                static fn (TaxGroup $group): array => [
                    'tax_type' => $group->type,
                    'rate' => $group->rate,
                    'base' => $group->base,
                    'amount' => $group->amount,
                    'exemption_code' => $group->exemptionCode,
                ],
                $breakdown->allGroups(),
            ));

            $document->forceFill([
                'taxable_base' => $breakdown->taxableBase,
                'vat_total' => $breakdown->vatTotal,
                'surcharge_total' => $breakdown->surchargeTotal,
                'irpf_total' => $breakdown->irpfTotal,
                'total' => $breakdown->total,
            ])->save();

            $document->unsetRelation('lines')->unsetRelation('taxes');

            return $breakdown;
        });
    }

    /** Régimen del emisor, recargo y retención del cliente y descuento global. */
    private function contextFor(Document $document): TaxContext
    {
        $customer = $document->customer;

        return new TaxContext(
            currency: $document->currency,
            globalDiscount: $document->global_discount_percent,
            irpfRate: $document->irpf_rate,
            issuerRegime: Issuer::current()->vat_regime,
            customerSurchargeApplies: $customer?->surcharge_applies ?? false,
            customerIrpfApplies: $customer?->appliesIrpf() ?? false,
            allowNegativeQuantities: $document instanceof CreditNote,
        );
    }

    private static function toLineInput(DocumentLine $line): LineInput
    {
        return new LineInput(
            $line->quantity,
            $line->unit_price,
            $line->discount_percent,
            $line->vat_rate,
            $line->surcharge_rate,
            $line->irpf_applies,
            $line->exemption_code,
        );
    }
}
