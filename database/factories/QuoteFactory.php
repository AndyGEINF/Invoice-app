<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Quote;
use Carbon\CarbonImmutable;

/**
 * @extends DocumentFactory<Quote>
 */
final class QuoteFactory extends DocumentFactory
{
    protected $model = Quote::class;

    /** Validez por defecto de un presupuesto, igual que en config/invoice.php. */
    private const int VALIDITY_DAYS = 30;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'type' => Quote::fixedType(),
            'status' => QuoteStatus::Draft->value,
            'issue_date' => CarbonImmutable::today(),
            'valid_until' => CarbonImmutable::today()->addDays(self::VALIDITY_DAYS),
        ];
    }

    public function sent(): self
    {
        return $this->withTotals()->state(fn (): array => ['status' => QuoteStatus::Sent->value]);
    }

    public function accepted(): self
    {
        return $this->withTotals()->state(fn (): array => ['status' => QuoteStatus::Accepted->value]);
    }

    public function expired(): self
    {
        return $this->sent()->state(fn (): array => [
            'issue_date' => CarbonImmutable::today()->subDays(self::VALIDITY_DAYS * 2),
            'valid_until' => CarbonImmutable::yesterday(),
        ]);
    }
}
