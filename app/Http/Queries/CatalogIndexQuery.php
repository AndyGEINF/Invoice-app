<?php

declare(strict_types=1);

namespace App\Http\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Filtros de los listados de clientes y de productos: `q` (texto) y `archived`
 * (ver los archivados en vez de los activos). Un valor raro se ignora.
 */
final readonly class CatalogIndexQuery
{
    public const int PER_PAGE = 25;

    /** Resultados de los buscadores incrementales (`/customers/search`, `/products/search`). */
    public const int SEARCH_LIMIT = 20;

    public const int MAX_SEARCH_LENGTH = 100;

    /** Caracteres comodín de LIKE que se buscan de forma literal. */
    private const string LIKE_WILDCARDS = '%_\\';

    public function __construct(
        public ?string $search = null,
        public bool $archived = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $search = trim((string) $request->query('q', ''));

        return new self(
            search: $search === '' ? null : mb_substr($search, 0, self::MAX_SEARCH_LENGTH),
            archived: $request->boolean('archived'),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns  Columnas en las que se busca el texto.
     * @return Builder<TModel>
     */
    public function apply(Builder $query, array $columns): Builder
    {
        $query->when(
            $this->archived,
            fn (Builder $q) => $q->whereNotNull('archived_at'),
            fn (Builder $q) => $q->whereNull('archived_at'),
        );

        return $this->search($query, $columns);
    }

    /**
     * Solo el texto, sin mirar si está archivado.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    public function search(Builder $query, array $columns): Builder
    {
        if ($this->search === null) {
            return $query;
        }

        $like = '%'.addcslashes($this->search, self::LIKE_WILDCARDS).'%';

        return $query->where(function (Builder $where) use ($columns, $like): void {
            foreach ($columns as $column) {
                $where->orWhere($column, 'ilike', $like);
            }
        });
    }

    /** @return array{q: string|null, archived: bool} */
    public function toArray(): array
    {
        return ['q' => $this->search, 'archived' => $this->archived];
    }
}
