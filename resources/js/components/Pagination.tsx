import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/documents';

/** "1–25 de 80" y Anterior / Siguiente. No se pinta si todo cabe en una página. */
export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav aria-label="Paginación" className="mt-4 flex items-center justify-between text-sm">
            <span className="text-muted-foreground">
                {page.from}–{page.to} de {page.total}
            </span>
            <div className="flex gap-2">
                <PageLink href={page.prev_page_url} label="Anterior" icon="prev" />
                <PageLink href={page.next_page_url} label="Siguiente" icon="next" />
            </div>
        </nav>
    );
}

function PageLink({ href, label, icon }: { href: string | null; label: string; icon: 'prev' | 'next' }) {
    const Icon = icon === 'prev' ? ChevronLeft : ChevronRight;

    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                <Icon aria-hidden />
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                <Icon aria-hidden />
                {label}
            </Link>
        </Button>
    );
}
