<?php

declare(strict_types=1);

namespace App\Http\Queries;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Shared\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Filtros del listado de facturas y rectificativas: `q, status, payment_status,
 * customer_id, from, to, series_id` (contracts/web-routes.md).
 *
 * Es un GET que viene de la barra de filtros o de un enlace guardado: un valor
 * inválido se ignora en lugar de devolver un error.
 */
final readonly class DocumentIndexQuery
{
    public const int PER_PAGE = 25;

    public const int MAX_SEARCH_LENGTH = 100;

    private const string DATE_FORMAT = 'Y-m-d';

    /** Caracteres comodín de LIKE que se buscan de forma literal. */
    private const string LIKE_WILDCARDS = '%_\\';

    public function __construct(
        public ?string $search = null,
        public ?DocumentStatus $status = null,
        public ?PaymentStatus $paymentStatus = null,
        public ?string $customerId = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?string $seriesId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $search = trim((string) $request->query('q', ''));

        return new self(
            search: $search === '' ? null : mb_substr($search, 0, self::MAX_SEARCH_LENGTH),
            status: DocumentStatus::tryFrom((string) $request->query('status', '')),
            paymentStatus: PaymentStatus::tryFrom((string) $request->query('payment_status', '')),
            customerId: self::uuidOrNull($request->query('customer_id')),
            from: self::dateOrNull($request->query('from')),
            to: self::dateOrNull($request->query('to')),
            seriesId: self::uuidOrNull($request->query('series_id')),
        );
    }

    /**
     * @template TModel of Document
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, CarbonImmutable $today): Builder
    {
        if ($this->search !== null) {
            $like = '%'.addcslashes($this->search, self::LIKE_WILDCARDS).'%';

            $query->where(fn (Builder $where) => $where
                ->where('full_number', 'ilike', $like)
                ->orWhereRaw("customer_snapshot->>'legal_name' ilike ?", [$like])
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('legal_name', 'ilike', $like)
                    ->orWhere('trade_name', 'ilike', $like)
                    ->orWhere('tax_id', 'ilike', $like)));
        }

        if ($this->status !== null) {
            $query->where('status', $this->status->value);
        }

        match ($this->paymentStatus) {
            PaymentStatus::Paid => $query->paid(),
            PaymentStatus::Unpaid => $query->unpaid($today),
            PaymentStatus::Overdue => $query->overdue($today),
            null => null,
        };

        if ($this->customerId !== null) {
            $query->where('customer_id', $this->customerId);
        }

        if ($this->from !== null) {
            $query->where('issue_date', '>=', $this->from->toDateString());
        }

        if ($this->to !== null) {
            $query->where('issue_date', '<=', $this->to->toDateString());
        }

        if ($this->seriesId !== null) {
            $query->where('series_id', $this->seriesId);
        }

        return $query;
    }

    /**
     * Totales de todo lo filtrado (no solo de la página): número de documentos,
     * suma de los emitidos, lo pendiente de cobro aún en plazo y lo vencido.
     * Pendiente y vencido no se solapan: juntos son todo lo no cobrado.
     *
     * @param  Builder<covariant Document>  $filtered
     * @return array<string, int|string>
     */
    public static function totals(Builder $filtered, CarbonImmutable $today): array
    {
        $issued = (clone $filtered)->where('status', '<>', DocumentStatus::Draft->value);

        $sumTotal = Money::fromCents((int) (clone $issued)->sum('total'));
        $sumPending = Money::fromCents((int) (clone $filtered)->unpaid($today)->sum('total'));
        $sumOverdue = Money::fromCents((int) (clone $filtered)->overdue($today)->sum('total'));

        return [
            'count' => (clone $filtered)->count(),
            'sum_total' => $sumTotal->cents,
            'sum_total_formatted' => $sumTotal->format(),
            'sum_pending' => $sumPending->cents,
            'sum_pending_formatted' => $sumPending->format(),
            'sum_overdue' => $sumOverdue->cents,
            'sum_overdue_formatted' => $sumOverdue->format(),
        ];
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'status' => $this->status?->value,
            'payment_status' => $this->paymentStatus?->value,
            'customer_id' => $this->customerId,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'series_id' => $this->seriesId,
        ];
    }

    private static function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    private static function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== null && $date->format(self::DATE_FORMAT) === $value ? $date : null;
    }
}
