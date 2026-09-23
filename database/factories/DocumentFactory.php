<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customers\Customer;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory común de presupuestos, facturas y rectificativas.
 *
 * Las subclases ({@see InvoiceFactory}, {@see QuoteFactory},
 * {@see CreditNoteFactory}) fijan el tipo. Los importes por defecto cuadran con
 * la restricción de la base de datos: total = base + IVA + recargo − IRPF.
 *
 * @template TModel of Document
 *
 * @extends Factory<TModel>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /** Contador compartido para que los números emitidos no choquen entre sí. */
    private static int $nextNumber = DocumentNumber::FIRST_NUMBER;

    public function definition(): array
    {
        return [
            'type' => DocumentType::Invoice,
            'status' => DocumentStatus::Draft->value,
            'customer_id' => Customer::factory(),
            'currency' => 'EUR',
            'global_discount_percent' => '0.00',
            'irpf_rate' => '0.00',
            'taxable_base' => Money::zero(),
            'vat_total' => Money::zero(),
            'surcharge_total' => Money::zero(),
            'irpf_total' => Money::zero(),
            'total' => Money::zero(),
        ];
    }

    public function quote(): static
    {
        return $this->state(fn (): array => [
            'type' => DocumentType::Quote,
            'status' => QuoteStatus::Draft->value,
        ]);
    }

    public function invoice(): static
    {
        return $this->state(fn (): array => [
            'type' => DocumentType::Invoice,
            'status' => DocumentStatus::Draft->value,
        ]);
    }

    public function creditNote(?Document $rectifies = null): static
    {
        return $this->state(fn (): array => [
            'type' => DocumentType::CreditNote,
            'status' => DocumentStatus::Draft->value,
            'rectifies_id' => $rectifies->id ?? Document::factory()->issued(),
            'rectification_type' => RectificationType::Substitution,
            'rectification_reason' => 'Error en la cantidad facturada',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => DocumentStatus::Draft->value]);
    }

    /** Importes de 100,00 € de base al 21 %: total 121,00 €. */
    public function withTotals(int $baseCents = 10000, int $vatCents = 2100): static
    {
        return $this->state(fn (): array => [
            'taxable_base' => Money::fromCents($baseCents),
            'vat_total' => Money::fromCents($vatCents),
            'total' => Money::fromCents($baseCents + $vatCents),
        ]);
    }

    /**
     * Documento emitido: número, fecha, snapshots y clasificación fiscal.
     *
     * No pasa por el caso de uso de emisión: sirve para preparar datos de prueba.
     */
    public function issued(?CarbonImmutable $issueDate = null): static
    {
        return $this->withTotals()->state(function () use ($issueDate): array {
            $date = $issueDate ?? CarbonImmutable::today();
            $number = self::$nextNumber++;
            $year = (int) $date->format('Y');

            // El cliente aún es una factory en este punto: sus datos se calculan
            // con closures, que Laravel evalúa cuando ya está creado.
            $customerOf = static fn (array $attributes): ?Customer => Customer::query()->find($attributes['customer_id'] ?? null);

            return [
                'status' => DocumentStatus::Issued->value,
                'number' => $number,
                'fiscal_year' => $year,
                'full_number' => DocumentNumber::of('F', $number, DocumentNumber::DEFAULT_PADDING, $year)->full(),
                'issue_date' => $date,
                'due_date' => static fn (array $attributes): CarbonImmutable => $date->addDays(
                    $customerOf($attributes)->payment_terms_days ?? Customer::DEFAULT_PAYMENT_TERMS_DAYS
                ),
                'issuer_snapshot' => Issuer::factory()->complete()->make()->toSnapshot(),
                'customer_snapshot' => static fn (array $attributes): ?array => $customerOf($attributes)?->toSnapshot(),
                'invoice_type' => InvoiceType::F1,
                'issued_at' => $date,
            ];
        });
    }

    public function inSeries(Series $series): static
    {
        return $this->state(fn (): array => ['series_id' => $series->id]);
    }

    public function paid(?CarbonImmutable $on = null): static
    {
        return $this->state(fn (): array => [
            'paid_at' => $on ?? CarbonImmutable::today(),
            'paid_note' => 'Transferencia',
        ]);
    }

    public function overdue(): static
    {
        return $this->issued(CarbonImmutable::today()->subDays(60))
            ->state(fn (): array => ['due_date' => CarbonImmutable::today()->subDays(30)]);
    }
}
