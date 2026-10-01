import { Link, usePage } from '@inertiajs/react';
import { FileMinus, FileSignature, FileText, LayoutDashboard, Menu, Package, Settings, Users } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { FlashMessages } from '@/components/FlashMessages';
import { IssuerIncompleteBanner } from '@/components/IssuerIncompleteBanner';
import { ThemeToggle } from '@/components/ThemeToggle';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface NavItem {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
}

const DASHBOARD: NavItem = { label: 'Panel', href: '/dashboard', icon: LayoutDashboard };
const QUOTES: NavItem = { label: 'Presupuestos', href: '/quotes', icon: FileSignature };
const INVOICES: NavItem = { label: 'Facturas', href: '/invoices', icon: FileText };
const CREDIT_NOTES: NavItem = { label: 'Rectificativas', href: '/credit-notes', icon: FileMinus };
const CUSTOMERS: NavItem = { label: 'Clientes', href: '/customers', icon: Users };
const PRODUCTS: NavItem = { label: 'Catálogo', href: '/products', icon: Package };
const SETTINGS: NavItem = { label: 'Ajustes', href: '/settings/issuer', icon: Settings };

/** Barra lateral (escritorio). */
const SIDEBAR: NavItem[] = [DASHBOARD, QUOTES, INVOICES, CREDIT_NOTES, CUSTOMERS, PRODUCTS];

/** Barra inferior (móvil): lo más usado; el resto en "Más". */
const BOTTOM_BAR: NavItem[] = [{ ...DASHBOARD, label: 'Inicio' }, INVOICES, QUOTES, CUSTOMERS];
const MORE: NavItem[] = [CREDIT_NOTES, PRODUCTS, SETTINGS];

function isActive(currentUrl: string, href: string): boolean {
    const path = currentUrl.split('?')[0] ?? '';

    return path === href || path.startsWith(`${href}/`) || (href === SETTINGS.href && path.startsWith('/settings'));
}

/**
 * Estructura común: barra lateral de iconos en escritorio,
 * barra inferior tipo app en móvil, aviso de datos del emisor incompletos y
 * mensajes de la última acción.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    const { url } = usePage();

    return (
        <TooltipProvider delayDuration={200}>
            <div className="min-h-screen bg-background">
                <DesktopSidebar url={url} />
                <MobileTopBar />

                <div className="md:pl-16">
                    <IssuerIncompleteBanner />
                    {/* Aprovecha pantallas anchas: hasta 1920 px; solo en 4K queda margen a los lados. */}
                    <main className="mx-auto w-full max-w-[120rem] px-4 pt-6 pb-24 md:px-8 md:pb-10">
                        <FlashMessages />
                        {children}
                    </main>
                </div>

                <MobileBottomBar url={url} />
            </div>
        </TooltipProvider>
    );
}

function AppMark() {
    return (
        <Link href={DASHBOARD.href} aria-label="Ir al panel" className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
            <FileText className="size-5" />
        </Link>
    );
}

/** Inicial o logotipo del emisor; lleva a sus datos. */
function IssuerAvatar({ className }: { className?: string }) {
    const { issuer, appName } = usePage().props;
    const name = issuer.legalName ?? appName;

    return (
        <Link
            href={SETTINGS.href}
            aria-label={`Datos de ${name}`}
            className={cn('relative flex size-9 items-center justify-center overflow-hidden rounded-full border bg-card text-sm font-semibold text-primary', className)}
        >
            {issuer.logoUrl ? <img src={issuer.logoUrl} alt="" className="size-full object-contain p-1" /> : name.charAt(0).toUpperCase()}
            <span
                aria-hidden
                className={cn('absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-card', issuer.isComplete ? 'bg-success' : 'bg-warning')}
            />
        </Link>
    );
}

function SidebarLink({ item, active }: { item: NavItem; active: boolean }) {
    const Icon = item.icon;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={item.href}
                    aria-label={item.label}
                    aria-current={active ? 'page' : undefined}
                    className={cn(
                        'flex size-10 items-center justify-center rounded-lg transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        active ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-foreground hover:bg-muted hover:text-foreground',
                    )}
                >
                    <Icon className="size-5" />
                </Link>
            </TooltipTrigger>
            <TooltipContent side="right">{item.label}</TooltipContent>
        </Tooltip>
    );
}

function DesktopSidebar({ url }: { url: string }) {
    const { issuer, appName } = usePage().props;

    return (
        <aside className="fixed inset-y-0 left-0 z-30 hidden w-16 flex-col items-center border-r border-sidebar-border bg-sidebar py-4 md:flex">
            <AppMark />

            <nav aria-label="Navegación principal" className="mt-6 flex flex-1 flex-col items-center gap-1.5">
                {SIDEBAR.map((item) => (
                    <SidebarLink key={item.href} item={item} active={isActive(url, item.href)} />
                ))}
            </nav>

            <div className="flex flex-col items-center gap-1.5">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span>
                            <ThemeToggle />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent side="right">Tema</TooltipContent>
                </Tooltip>
                <SidebarLink item={SETTINGS} active={isActive(url, SETTINGS.href)} />
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="mt-2">
                            <IssuerAvatar />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent side="right">{issuer.legalName ?? appName}</TooltipContent>
                </Tooltip>
            </div>
        </aside>
    );
}

function MobileTopBar() {
    const { issuer, appName } = usePage().props;

    return (
        <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-card/95 px-4 backdrop-blur md:hidden">
            <AppMark />
            <p className="min-w-0 flex-1 truncate text-sm font-semibold">{issuer.legalName ?? appName}</p>
            <IssuerAvatar />
        </header>
    );
}

function MobileBottomBar({ url }: { url: string }) {
    const moreActive = MORE.some((item) => isActive(url, item.href));

    return (
        <nav
            aria-label="Navegación principal"
            className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        >
            {BOTTOM_BAR.map((item) => (
                <BottomBarLink key={item.href} item={item} active={isActive(url, item.href)} />
            ))}

            <Sheet>
                <SheetTrigger className={cn('flex flex-col items-center gap-0.5 py-2 text-[11px]', moreActive ? 'text-primary' : 'text-muted-foreground')}>
                    <Menu className="size-5" />
                    Más
                </SheetTrigger>
                <SheetContent side="bottom" className="rounded-t-2xl pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <SheetHeader>
                        <SheetTitle>Más</SheetTitle>
                    </SheetHeader>
                    <div className="flex flex-col gap-1 px-4">
                        {MORE.map((item) => {
                            const Icon = item.icon;
                            const active = isActive(url, item.href);

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm',
                                        active ? 'bg-accent text-accent-foreground' : 'hover:bg-muted',
                                    )}
                                >
                                    <Icon className="size-5" />
                                    {item.label}
                                </Link>
                            );
                        })}
                        <ThemeToggle showLabel />
                    </div>
                </SheetContent>
            </Sheet>
        </nav>
    );
}

function BottomBarLink({ item, active }: { item: NavItem; active: boolean }) {
    const Icon = item.icon;

    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={cn('flex flex-col items-center gap-0.5 py-2 text-[11px]', active ? 'text-primary' : 'text-muted-foreground')}
        >
            <Icon className="size-5" />
            {item.label}
        </Link>
    );
}
