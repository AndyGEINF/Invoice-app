import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/documents';

/** Las mismas que admite el servidor (`App\Http\Queries\PerPage::OPTIONS`). */
const PAGE_SIZES = [10, 25, 50];

/** Separador de páginas saltadas que manda el paginador de Laravel. */
const GAP_LABEL = '...';

/**
 * Pie de los listados: "1–10 de 37", filas por página y las páginas. Se ve
 * siempre que haya algo; con una sola página los botones quedan desactivados.
 */
export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.total === 0) {
        return null;
    }

    // El primer y el último enlace son Anterior y Siguiente; en medio, las páginas.
    const pages = page.links.slice(1, -1);

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
                    {pages.map((link, index) =>
                        link.label === GAP_LABEL || !link.url ? (
                            <span key={`gap-${index}`} className="px-1 text-muted-foreground" aria-hidden>
                                …
                            </span>
                        ) : (
                            <Button
                                key={link.label}
                                variant={link.active ? 'default' : 'ghost'}
                                size="icon-sm"
                                asChild
                                className={cn('tabular-nums', link.active && 'pointer-events-none')}
                            >
                                <Link href={link.url} preserveScroll aria-current={link.active ? 'page' : undefined} aria-label={`Página ${link.label}`}>
                                    {link.label}
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
