<?php

declare(strict_types=1);

namespace App\Application\Documents\Data;

use App\Domain\Catalog\Product;
use App\Domain\Shared\Currency;
use App\Domain\Shared\Percentage;
use Carbon\CarbonImmutable;

/**
 * Datos de creación o edición de un borrador (`DraftForm` de
 * contracts/web-routes.md), ya validados por el form request.
 */
final readonly class DraftData
{
    /** @param list<LineData> $lines */
    public function __construct(
        public ?string $customerId,
        public ?string $seriesId,
        public array $lines,
        public Currency $currency = Currency::EUR,
        public ?Percentage $globalDiscount = null,
        public ?Percentage $irpfRate = null,
        public ?CarbonImmutable $issueDate = null,
        public ?CarbonImmutable $validUntil = null,
        public ?CarbonImmutable $dueDate = null,
        public ?CarbonImmutable $operationDate = null,
        public ?string $notes = null,
        public ?string $internalNotes = null,
    ) {}

    /** @param array<string, mixed> $form */
    public static function fromArray(array $form): self
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = array_values($form['lines'] ?? []);

        $products = Product::query()
            ->whereKey(array_filter(array_column($lines, 'product_id')))
            ->get()
            ->keyBy('id');

        return new self(
            customerId: self::stringOrNull($form['customer_id'] ?? null),
            seriesId: self::stringOrNull($form['series_id'] ?? null),
            lines: array_map(
                static fn (array $line): LineData => LineData::fromArray($line, $products->get($line['product_id'] ?? null)),
                $lines,
            ),
            currency: Currency::from((string) ($form['currency'] ?? Currency::EUR->value)),
            globalDiscount: Percentage::of((string) ($form['global_discount_percent'] ?? Percentage::MIN)),
            irpfRate: Percentage::of((string) ($form['irpf_rate'] ?? Percentage::MIN)),
            issueDate: self::dateOrNull($form['issue_date'] ?? null),
            validUntil: self::dateOrNull($form['valid_until'] ?? null),
            dueDate: self::dateOrNull($form['due_date'] ?? null),
            operationDate: self::dateOrNull($form['operation_date'] ?? null),
            notes: self::stringOrNull($form['notes'] ?? null),
            internalNotes: self::stringOrNull($form['internal_notes'] ?? null),
        );
    }

    /**
     * Columnas de `documents` que fija el formulario. La serie, el número, los
     * snapshots y los totales los ponen otros casos de uso.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'customer_id' => $this->customerId,
            'series_id' => $this->seriesId,
            'currency' => $this->currency,
            'global_discount_percent' => $this->globalDiscount ?? Percentage::zero(),
            'irpf_rate' => $this->irpfRate ?? Percentage::zero(),
            'issue_date' => $this->issueDate,
            'valid_until' => $this->validUntil,
            'due_date' => $this->dueDate,
            'operation_date' => $this->operationDate,
            'notes' => $this->notes,
            'internal_notes' => $this->internalNotes,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private static function dateOrNull(mixed $value): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->startOfDay();
    }
}
