<?php

declare(strict_types=1);

namespace App\Domain\Documents\Concerns;

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comportamiento común de facturas y rectificativas: estado fiscal e
 * inmutabilidad tras emitir, y estado de cobro derivado del marcador visual.
 *
 * La aplicación no gestiona pagos: "cobrada" es una marca que pone el usuario, y
 * "vencida" se deduce de la fecha de vencimiento (constitución, principio VII).
 */
trait IsFiscalDocument
{
    public function initializeIsFiscalDocument(): void
    {
        $this->mergeCasts(['status' => DocumentStatus::class]);
        $this->attributes['status'] ??= DocumentStatus::Draft->value;
    }

    public function isEditable(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status !== DocumentStatus::Draft;
    }

    /**
     * Estado de cobro. Null mientras el documento es un borrador.
     */
    public function paymentStatus(?CarbonImmutable $today = null): ?PaymentStatus
    {
        if (! $this->isIssued()) {
            return null;
        }

        if ($this->paid_at !== null || $this->total->isZero()) {
            return PaymentStatus::Paid;
        }

        $today ??= CarbonImmutable::today();

        // Días de calendario, no instantes: no influye la zona horaria de cada fecha.
        if ($this->due_date !== null && $this->due_date->toDateString() < $today->toDateString()) {
            return PaymentStatus::Overdue;
        }

        return PaymentStatus::Unpaid;
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus() === PaymentStatus::Paid;
    }

    public function isOverdue(): bool
    {
        return $this->paymentStatus() === PaymentStatus::Overdue;
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function issued(Builder $query): void
    {
        $query->where('status', '<>', DocumentStatus::Draft->value);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function paid(Builder $query): void
    {
        $query->where('status', '<>', DocumentStatus::Draft->value)
            ->where(fn (Builder $q) => $q->whereNotNull('paid_at')->orWhere('total', 0));
    }

    /** Emitidas sin marcar como cobradas y aún dentro de plazo. */
    #[Scope]
    protected function unpaid(Builder $query, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();

        $this->whereIssuedAndNotPaid($query)
            ->where(fn (Builder $q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today->toDateString()));
    }

    /** Emitidas sin marcar como cobradas y con el vencimiento superado. */
    #[Scope]
    protected function overdue(Builder $query, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();

        $this->whereIssuedAndNotPaid($query)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today->toDateString());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    private function whereIssuedAndNotPaid(Builder $query): Builder
    {
        return $query->where('status', '<>', DocumentStatus::Draft->value)
            ->whereNull('paid_at')
            ->where('total', '<>', 0);
    }
}
