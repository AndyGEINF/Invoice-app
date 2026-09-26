<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\DraftData;
use App\Application\Documents\Data\LineData;
use App\Domain\Catalog\Product;
use App\Domain\Customers\Customer;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Contracts\Clock;
use Illuminate\Validation\ValidationException;

/**
 * Lo que comparten crear y editar un borrador: validar las reglas de negocio de
 * las líneas, volcar el formulario en el documento, sincronizar las líneas y
 * recalcular.
 *
 * El form request ya comprobó formatos y rangos; aquí van las reglas que
 * dependen del documento, del cliente o del emisor. Los errores salen como
 * errores de validación con la clave del campo (`lines.2.quantity`).
 */
final readonly class DraftWriter
{
    public function __construct(
        private RecalculateDocument $recalculate,
        private Clock $clock,
    ) {}

    public function write(Document $document, DraftData $data): void
    {
        // La serie se resuelve una sola vez: la usan la validación y el volcado.
        $series = $this->seriesFor($document, $data);

        $this->validate($document, $data, $series);

        $document->fill($this->documentAttributes($document, $data, $series));
        $document->save();

        $this->syncLines($document, $data->lines);

        ($this->recalculate)($document);
    }

    private function validate(Document $document, DraftData $data, ?Series $series): void
    {
        $errors = [];

        if ($data->customerId !== null && $data->customerId !== $document->getOriginal('customer_id')) {
            $customer = Customer::query()->find($data->customerId);

            if ($customer === null) {
                $errors['customer_id'] = 'El cliente no existe.';
            } elseif ($customer->isArchived()) {
                $errors['customer_id'] = 'El cliente está archivado. Restáuralo para poder facturarle.';
            }
        }

        if ($series === null) {
            $errors['series_id'] = 'No hay ninguna serie para este tipo de documento.';
        } elseif ($series->document_type !== $document->type) {
            $errors['series_id'] = 'La serie no corresponde a este tipo de documento.';
        } elseif (! $series->is_active) {
            $errors['series_id'] = 'La serie está desactivada.';
        }

        $issuerExempt = Issuer::current()->vat_regime->isExempt();
        $allowsNegatives = $document instanceof CreditNote;
        $existingProducts = $this->existingProductIds($data->lines);

        foreach ($data->lines as $index => $line) {
            $field = "lines.{$index}";

            if (trim($line->description) === '') {
                $errors["{$field}.description"] = 'La línea necesita una descripción.';
            }

            if ($line->quantity->isNegative() && ! $allowsNegatives) {
                $errors["{$field}.quantity"] = 'La cantidad no puede ser negativa. Para restar importes, emite una rectificativa.';
            }

            if (($line->vatRate->isZero() || $issuerExempt) && $line->exemptionCode === null) {
                $errors["{$field}.exemption_code"] = 'Una línea sin IVA necesita la causa de exención o marcarse como no sujeta.';
            }

            if ($line->productId !== null && ! isset($existingProducts[$line->productId])) {
                $errors["{$field}.product_id"] = 'El producto no existe.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    private function documentAttributes(Document $document, DraftData $data, ?Series $series): array
    {
        $attributes = $data->toAttributes();
        $attributes['series_id'] = $series?->id;

        if ($document instanceof Quote) {
            $issueDate = $data->issueDate ?? $document->issue_date ?? $this->clock->today();
            $attributes['issue_date'] = $issueDate;
            $attributes['valid_until'] = $data->validUntil
                ?? $issueDate->addDays((int) config('invoice.quote.default_validity_days'));
            $attributes['due_date'] = null;
        } else {
            // La fecha de expedición de una factura la fija la emisión.
            unset($attributes['issue_date'], $attributes['valid_until']);
        }

        return $attributes;
    }

    private function seriesFor(Document $document, DraftData $data): ?Series
    {
        if ($data->seriesId !== null) {
            return Series::query()->find($data->seriesId);
        }

        return $document->series_id !== null
            ? Series::query()->find($document->series_id)
            : Series::defaultFor($document->type ?? DocumentType::Invoice);
    }

    /**
     * Actualiza las líneas que ya existen (por id), crea las nuevas y borra las
     * que el formulario ya no trae. Así las líneas conservan su identidad entre
     * ediciones.
     *
     * @param  list<LineData>  $lines
     */
    private function syncLines(Document $document, array $lines): void
    {
        /** @var array<string, DocumentLine> $existing */
        $existing = $document->lines()->get()->keyBy('id')->all();
        $kept = [];

        foreach ($lines as $line) {
            $model = $line->id !== null ? ($existing[$line->id] ?? null) : null;

            if ($model === null) {
                $document->lines()->create($line->toAttributes());

                continue;
            }

            $model->fill($line->toAttributes())->save();
            $kept[$model->id] = true;
        }

        foreach (array_diff_key($existing, $kept) as $removed) {
            $removed->delete();
        }

        $document->unsetRelation('lines');
    }

    /**
     * Productos de las líneas que existen, en una sola consulta en vez de una
     * por línea.
     *
     * @param  list<LineData>  $lines
     * @return array<string, true>
     */
    private function existingProductIds(array $lines): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (LineData $line): ?string => $line->productId, $lines),
            static fn (?string $id): bool => $id !== null,
        )));

        if ($ids === []) {
            return [];
        }

        return array_fill_keys(
            Product::query()->whereKey($ids)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all(),
            true,
        );
    }
}
