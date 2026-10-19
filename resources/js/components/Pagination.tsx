import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Paginated } from '@/types/documents';

/** Las mismas que admite el servidor (`App\Http\Queries\PerPage::OPTIONS`). */
const PAGE_SIZES = [10, 25, 50];

/** Páginas seguidas que se ven al principio o al final antes del salto: "1 2 3 4 5 … 17 18". */
const EDGE_RUN = 5;

/** Páginas que se ven siempre a cada extremo cuando hay salto ("… 17 18"). */
const EDGE_TAIL = 2;

/** Con estas páginas o menos se ven todas, sin saltos. */
const SHOW_ALL_UP_TO = EDGE_RUN + EDGE_TAIL + 1;

type PageItem = number | 'gap';

/**
 * Qué páginas enseñar. Cerca del principio: 1–5 … y las dos últimas. Cerca del
 * final, al revés. En medio: la primera, la actual con sus vecinas y la última.
 */
function pageItems(current: number, last: number): PageItem[] {
    const range = (from: number, to: number) => Array.from({ length: to - from + 1 }, (_, i) => from + i);

    if (last <= SHOW_ALL_UP_TO) {
        return range(1, last);
    }

    if (current < EDGE_RUN) {
        return [...range(1, EDGE_RUN), 'gap', ...range(last - EDGE_TAIL + 1, last)];
    }

    if (current > last - EDGE_RUN + 1) {
        return [...range(1, EDGE_TAIL), 'gap', ...range(last - EDGE_RUN + 1, last)];
    }

    return [1, 'gap', current - 1, current, current + 1, 'gap', last];
}

/** URL de una página con los mismos filtros y filas por página que la actual. */
function pageUrl(path: string, page: number): string {
    const query = new URLSearchParams(window.location.search);
    query.set('page', String(page));

    return `${path}?${query.toString()}`;
}

/**
 * Pie de los listados: "1–10 de 37", filas por página y las páginas. Se ve
 * siempre que haya algo; con una sola página los botones quedan desactivados.
 * En móvil las páginas numeradas se cambian por "Página 3 de 18", que siempre cabe.
 */
export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.total === 0) {
        return null;
    }

    function changePageSize(value: string) {
        const query = Object.fromEntries(new URLSearchParams(window.location.search));
        delete query.page;
        router.get(page.path, { ...query, per_page: value }, { preserveScroll: true, preserveState: true });
    }

    return (
        <nav aria-label="Paginación" className="mt-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 text-sm">
            <span className="text-muted-foreground tabular-nums">
                {page.from}–{page.to} de {page.total}
            </span>

            <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                <label className="flex items-center gap-2 text-muted-foreground">
                    Filas por página
                    <Select value={String(page.per_page)} onValueChange={changePageSize}>
                        <SelectTrigger size="sm" className="w-18 text-foreground" aria-label="Filas por página">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PAGE_SIZES.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </label>

                <div className="flex items-center gap-1">
                    <PageArrow href={page.prev_page_url} label="Página anterior" direction="prev" />

                    <span className="px-2 text-muted-foreground tabular-nums sm:hidden">
                        Página {page.current_page} de {page.last_page}
                    </span>

                    {pageItems(page.current_page, page.last_page).map((item, index) =>
                        item === 'gap' ? (
                            <span key={`gap-${index}`} className="px-1 text-muted-foreground max-sm:hidden" aria-hidden>
                                …
                            </span>
                        ) : (
                            <Button
                                key={item}
                                variant={item === page.current_page ? 'default' : 'ghost'}
                                size="icon-sm"
                                asChild
                                className="tabular-nums max-sm:hidden"
                            >
                                <Link
                                    href={pageUrl(page.path, item)}
                                    preserveScroll
                                    aria-current={item === page.current_page ? 'page' : undefined}
                                    aria-label={`Página ${item}`}
                                    className={item === page.current_page ? 'pointer-events-none' : undefined}
                                >
                                    {item}
                                </Link>
                            </Button>
                        ),
                    )}

                    <PageArrow href={page.next_page_url} label="Página siguiente" direction="next" />
                </div>
            </div>
        </nav>
    );
}

function PageArrow({ href, label, direction }: { href: string | null; label: string; direction: 'prev' | 'next' }) {
    const Icon = direction === 'prev' ? ChevronLeft : ChevronRight;

    if (!href) {
        return (
            <Button variant="ghost" size="icon-sm" disabled aria-label={label}>
                <Icon aria-hidden />
            </Button>
        );
    }

    return (
        <Button variant="ghost" size="icon-sm" asChild>
            <Link href={href} preserveScroll aria-label={label}>
                <Icon aria-hidden />
            </Link>
        </Button>
    );
}
