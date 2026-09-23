import { Link, usePage } from '@inertiajs/react';
import { FileText, FileMinus, FileSignature, LayoutDashboard, Package, Settings, Users } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { IssuerIncompleteBanner } from '@/components/IssuerIncompleteBanner';
import { cn } from '@/lib/utils';

interface NavItem {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
}

const NAVIGATION: NavItem[] = [
    { label: 'Panel', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Presupuestos', href: '/quotes', icon: FileSignature },
    { label: 'Facturas', href: '/invoices', icon: FileText },
    { label: 'Rectificativas', href: '/credit-notes', icon: FileMinus },
    { label: 'Clientes', href: '/customers', icon: Users },
    { label: 'Catálogo', href: '/products', icon: Package },
    { label: 'Ajustes', href: '/settings/issuer', icon: Settings },
];

function isActive(currentUrl: string, href: string): boolean {
    const path = currentUrl.split('?')[0] ?? '';

    return path === href || path.startsWith(`${href}/`) || (href === '/settings/issuer' && path.startsWith('/settings'));
}

/**
 * Estructura común de todas las páginas: cabecera con el emisor, navegación y
 * aviso de datos incompletos.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const { issuer, appName } = props;

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
                    {issuer.logoUrl ? (
                        <img src={issuer.logoUrl} alt="" className="h-8 max-w-32 object-contain" />
                    ) : null}
                    <div className="min-w-0">
                        <p className="truncate font-semibold">{issuer.legalName ?? appName}</p>
                        {issuer.companyName && issuer.name ? (
                            <p className="truncate text-xs text-muted-foreground">{issuer.name}</p>
                        ) : null}
                    </div>
                </div>

                <nav aria-label="Navegación principal" className="mx-auto max-w-6xl overflow-x-auto px-2">
                    <ul className="flex gap-1">
                        {NAVIGATION.map(({ label, href, icon: Icon }) => {
                            const active = isActive(url, href);

                            return (
                                <li key={href}>
                                    <Link
                                        href={href}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'flex items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                            active
                                                ? 'border-primary font-medium text-foreground'
                                                : 'border-transparent text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        <Icon className="size-4" />
                                        {label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>
            </header>

            <IssuerIncompleteBanner />

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">{children}</main>
        </div>
    );
}
