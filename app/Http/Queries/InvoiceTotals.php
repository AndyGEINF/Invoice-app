<?php

declare(strict_types=1);

namespace App\Http\Queries;

use App\Domain\Documents\Invoice;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sumas de facturas para tarjetas y listados: lo emitido, lo pendiente de cobro
 * aún en plazo y lo vencido. Pendiente y vencido no se solapan; el estado de
 * cobro se deriva, nunca se guarda.
 */
final readonly class InvoiceTotals
{
    /**
     * `{key}_count`, `{key}_total` (céntimos) y `{key}_total_formatted`.
     *
     * @param  Builder<Invoice>  $query
     * @return array<string, int|string>
     */
    public static function stat(string $key, Builder $query): array
    {
        $total = Money::fromCents((int) (clone $query)->sum('total'));

        return [
            "{$key}_count" => (clone $query)->count(),
            "{$key}_total" => $total->cents,
            "{$key}_total_formatted" => $total->format(),
        ];
    }

    /** Facturas emitidas en el año de `$today`, opcionalmente de un solo cliente. */
    public static function issuedThisYear(CarbonImmutable $today, ?string $customerId = null): Builder
    {
        return Invoice::query()
            ->issued()
            ->when($customerId !== null, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->whereBetween('issue_date', [$today->startOfYear()->toDateString(), $today->endOfYear()->toDateString()]);
    }

    /**
     * Pendiente (en plazo) y vencido de cada cliente, en céntimos.
     *
     * @param  list<string>  $customerIds
     * @return array<string, array{pending: int, overdue: int}>
     */
    public static function unpaidByCustomer(array $customerIds, CarbonImmutable $today): array
    {
        if ($customerIds === []) {
            return [];
        }

        $sumBy = static fn (Builder $query): array => $query
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, sum(total) as amount')
            ->pluck('amount', 'customer_id')
            ->map(static fn (mixed $amount): int => (int) $amount)
            ->all();

        $pending = $sumBy(Invoice::query()->unpaid($today));
        $overdue = $sumBy(Invoice::query()->overdue($today));

        $result = [];

        foreach ($customerIds as $id) {
            $result[$id] = ['pending' => $pending[$id] ?? 0, 'overdue' => $overdue[$id] ?? 0];
        }

        return $result;
    }

    /**
     * Importes de un cliente listos para mostrar: lo no cobrado (pendiente +
     * vencido) y, aparte, lo vencido.
     *
     * @param  array{pending: int, overdue: int}  $amounts
     * @return array<string, int|string>
     */
    public static function unpaidProps(array $amounts): array
    {
        $unpaid = Money::fromCents($amounts['pending'] + $amounts['overdue']);
        $overdue = Money::fromCents($amounts['overdue']);

        return [
            'unpaid_total' => $unpaid->cents,
            'unpaid_total_formatted' => $unpaid->format(),
            'overdue_total' => $overdue->cents,
            'overdue_total_formatted' => $overdue->format(),
        ];
    }
}
