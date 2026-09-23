<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Series;
use App\Domain\Shared\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Series>
 */
final class SeriesFactory extends Factory
{
    protected $model = Series::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('???'));

        return [
            'document_type' => DocumentType::Invoice,
            'code' => $code,
            'prefix' => $code,
            'padding' => DocumentNumber::DEFAULT_PADDING,
            'next_number' => DocumentNumber::FIRST_NUMBER,
            'resets_yearly' => true,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /** Serie por defecto de facturas: F. */
    public function invoices(): self
    {
        return $this->state(fn (): array => [
            'document_type' => DocumentType::Invoice,
            'code' => 'F',
            'prefix' => 'F',
            'is_default' => true,
        ]);
    }

    /** Serie por defecto de rectificativas: R. */
    public function creditNotes(): self
    {
        return $this->state(fn (): array => [
            'document_type' => DocumentType::CreditNote,
            'code' => 'R',
            'prefix' => 'R',
            'is_default' => true,
        ]);
    }

    /** Serie por defecto de presupuestos: P. */
    public function quotes(): self
    {
        return $this->state(fn (): array => [
            'document_type' => DocumentType::Quote,
            'code' => 'P',
            'prefix' => 'P',
            'is_default' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
