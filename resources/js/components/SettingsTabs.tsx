import { Link, usePage } from '@inertiajs/react';

import { TAB_TRIGGER_CLASS } from '@/lib/tabs';
import { cn } from '@/lib/utils';

const TABS = [
    { href: '/settings/issuer', label: 'Tu empresa' },
    { href: '/settings/mail', label: 'Correo' },
];

/** Secciones de Ajustes, con el mismo aspecto que las pestañas del documento. */
export function SettingsTabs() {
    const { url } = usePage();

    return (
        <nav aria-label="Secciones de ajustes" className="mb-6 inline-flex h-9 items-center gap-1 rounded-lg bg-muted p-1">
            {TABS.map((tab) => {
                const active = url.startsWith(tab.href);

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        data-active={active ? '' : undefined}
                        aria-current={active ? 'page' : undefined}
                        className={cn('inline-flex h-full items-center rounded-md px-3 text-sm font-medium text-foreground/60 transition-colors', TAB_TRIGGER_CLASS)}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
