import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

/** Pestañas "Activos / Archivados" de los listados de clientes y productos. */
export function ArchivedTabs({ url, archived, counts }: { url: string; archived: boolean; counts: { active: number; archived: number } }) {
    const tabs = [
        { label: 'Activos', count: counts.active, href: url, current: !archived },
        { label: 'Archivados', count: counts.archived, href: `${url}?archived=1`, current: archived },
    ];

    return (
        <nav aria-label="Ver" className="inline-flex rounded-lg border bg-card p-0.5 text-sm">
            {tabs.map((tab) => (
                <Link
                    key={tab.label}
                    href={tab.href}
                    preserveScroll
                    aria-current={tab.current ? 'page' : undefined}
                    className={cn(
                        'rounded-md px-3 py-1.5 transition-colors',
                        tab.current ? 'bg-primary font-medium text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label} <span className="tabular-nums opacity-80">{tab.count}</span>
                </Link>
            ))}
        </nav>
    );
}
