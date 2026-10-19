import { Link, usePage } from '@inertiajs/react';
import { FileMinus, FileSignature, FileText, LayoutDashboard, Menu, Package, Settings, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
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

/** Icono de la marca (public/brand), generado desde Logo/Logo final. */
const APP_ICON = '/brand/app-icon.svg';

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

/** Barra lateral abierta o cerrada: se recuerda en este navegador. */
const SIDEBAR_STORAGE_KEY = 'invoice.sidebar';

/** Curva y duración del despliegue: arranca rápido y frena suave al llegar. */
const SIDEBAR_MOTION = 'duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none';

function readSidebarExpanded(): boolean {
    try {
        return localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'expanded';
    } catch {
        return false;
    }
}

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
    const [expanded, setExpanded] = useState(readSidebarExpanded);

    useEffect(() => {
        try {
            localStorage.setItem(SIDEBAR_STORAGE_KEY, expanded ? 'expanded' : 'collapsed');
        } catch {
            // Sin almacenamiento (modo privado…): se recuerda solo mientras dure la página.
        }
    }, [expanded]);

    return (
        <TooltipProvider delayDuration={200}>
            <div className="min-h-screen bg-background">
                <DesktopSidebar url={url} expanded={expanded} onToggle={() => setExpanded(!expanded)} />
                <MobileTopBar />

                {/* El margen acompaña al ancho de la barra, con la misma curva y duración. */}
                <div className={cn(SIDEBAR_MOTION, 'transition-[padding-left]', expanded ? 'md:pl-60' : 'md:pl-16')}>
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

/** Icono de INVOICE (dos hojas sobre marino noche). Sobre la barra lateral el fondo se funde y queda solo el símbolo. */
function AppMark() {
    return (
        <Link href={DASHBOARD.href} aria-label="Ir al panel" className="flex size-9 shrink-0 items-center justify-center">
            <img src={APP_ICON} alt="" className="size-9 rounded-lg" />
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
            {/* Sin margen: el logotipo ya viene recortado en círculo y llena el avatar. */}
            {issuer.logoUrl ? <img src={issuer.logoUrl} alt="" className="size-full rounded-full bg-white object-contain" /> : name.charAt(0).toUpperCase()}
            <span
                aria-hidden
                className={cn('absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-card', issuer.isComplete ? 'bg-success' : 'bg-warning')}
            />
        </Link>
    );
}

/**
 * Texto que acompaña a un icono de la barra: con la barra cerrada se oculta con
 * un fundido; al abrirla aparece un poco después, cuando ya hay sitio.
 */
function SidebarLabel({ expanded, children, className }: { expanded: boolean; children: ReactNode; className?: string }) {
    return (
        <span
            className={cn(
                'truncate whitespace-nowrap transition-opacity motion-reduce:transition-none',
                expanded ? 'opacity-100 delay-100 duration-200' : 'pointer-events-none opacity-0 duration-100',
                className,
            )}
        >
            {children}
        </span>
    );
}

/** Con la barra cerrada los nombres no se ven: el tooltip los dice. Abierta, sobra. */
function SidebarTooltip({ label, expanded, children }: { label: string; expanded: boolean; children: ReactNode }) {
    return (
        <Tooltip open={expanded ? false : undefined}>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="right">{label}</TooltipContent>
        </Tooltip>
    );
}

function SidebarLink({ item, active, expanded }: { item: NavItem; active: boolean; expanded: boolean }) {
    const Icon = item.icon;

    return (
        <SidebarTooltip label={item.label} expanded={expanded}>
            <Link
                href={item.href}
                aria-label={expanded ? undefined : item.label}
                aria-current={active ? 'page' : undefined}
                className={cn(
                    // El icono queda siempre en la misma columna: abierta o cerrada, no salta.
                    'flex h-10 items-center gap-3 overflow-hidden rounded-lg px-2.5 text-sm transition-colors outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                    active ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground' : 'text-sidebar-foreground hover:bg-sidebar-hover hover:text-sidebar-hover-foreground',
                )}
            >
                <Icon className="size-5 shrink-0" />
                <SidebarLabel expanded={expanded}>{item.label}</SidebarLabel>
            </Link>
        </SidebarTooltip>
    );
}

/**
 * Barra lateral de escritorio. Cerrada muestra solo los iconos; al tocar el
 * logotipo se despliega con los nombres, y al volver a tocarlo se recoge.
 */
function DesktopSidebar({ url, expanded, onToggle }: { url: string; expanded: boolean; onToggle: () => void }) {
    const { issuer, appName } = usePage().props;
    const issuerName = issuer.legalName ?? appName;

    return (
        <aside
            className={cn(
                SIDEBAR_MOTION,
                'fixed inset-y-0 left-0 z-30 hidden flex-col overflow-hidden border-r border-sidebar-border bg-sidebar px-3 py-4 transition-[width] md:flex',
                expanded ? 'w-60' : 'w-16',
            )}
        >
            <SidebarTooltip label="Desplegar menú" expanded={expanded}>
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={expanded}
                    aria-label={expanded ? 'Contraer menú' : 'Desplegar menú'}
                    // Textos de la barra alineados a 42 px del borde: icono de 40 (o 36 + 2 de margen) y su hueco.
                    className="flex h-10 items-center gap-1 rounded-lg pl-0.5 outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                >
                    <img src={APP_ICON} alt="" className="size-9 shrink-0 rounded-lg" />
                    <SidebarLabel expanded={expanded} className="text-base font-semibold tracking-wide text-sidebar-hover-foreground">
                        INVOICE
                    </SidebarLabel>
                </button>
            </SidebarTooltip>

            <nav aria-label="Navegación principal" className="mt-6 flex flex-1 flex-col gap-1.5">
                {SIDEBAR.map((item) => (
                    <SidebarLink key={item.href} item={item} active={isActive(url, item.href)} expanded={expanded} />
                ))}
            </nav>

            <div className="flex flex-col gap-1.5">
                <SidebarTooltip label="Tema" expanded={expanded}>
                    <span className="flex items-center gap-0.5">
                        {/* Sin shrink-0, con la barra cerrada el botón se encogía y el icono quedaba a la izquierda. */}
                        <ThemeToggle className="shrink-0" />
                        <SidebarLabel expanded={expanded} className="text-sm text-sidebar-foreground">
                            Tema
                        </SidebarLabel>
                    </span>
                </SidebarTooltip>
                <SidebarLink item={SETTINGS} active={isActive(url, SETTINGS.href)} expanded={expanded} />
                <SidebarTooltip label={issuerName} expanded={expanded}>
                    <span className="mt-2 flex items-center gap-1 pl-0.5">
                        <IssuerAvatar className="shrink-0" />
                        <SidebarLabel expanded={expanded} className="text-sm font-medium text-sidebar-hover-foreground">
                            {issuerName}
                        </SidebarLabel>
                    </span>
                </SidebarTooltip>
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
