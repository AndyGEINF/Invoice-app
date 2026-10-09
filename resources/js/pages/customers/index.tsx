import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FilePlus, Mail, MousePointerClick, Phone, Plus, Search } from 'lucide-react';
import type { ReactNode } from 'react';

import { ArchivedTabs } from '@/components/ArchivedTabs';
import { CustomerAvatar } from '@/components/customers/CustomerAvatar';
import { RowStatus } from '@/components/documents/StatusBadge';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { StatCard } from '@/components/StatCard';
import { StatusPill } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate } from '@/lib/dates';
import { DOCUMENT_SEGMENTS } from '@/lib/documents';
import { useDebouncedSearch } from '@/lib/use-debounced-search';
import { cn } from '@/lib/utils';
import type { CatalogCounts, CatalogFilters, CustomerIndexRow, CustomerIndexStats, SelectedCustomer, TopCustomer } from '@/types/catalog';
import type { DocumentRow, Paginated } from '@/types/documents';

interface Props {
    customers: Paginated<CustomerIndexRow>;
    filters: CatalogFilters;
    counts: CatalogCounts;
    stats: CustomerIndexStats;
    top_customers: TopCustomer[];
    pending_documents: DocumentRow[];
    selected: SelectedCustomer | null;
}

const CUSTOMERS_URL = '/customers';

/** Desde esta anchura el resumen del cliente se ve al lado de la tabla (xl de Tailwind). */
const SIDE_PANEL_QUERY = '(min-width: 1280px)';

function customersLabel(count: number): string {
    return count === 1 ? '1 cliente' : `${count} clientes`;
}

function invoicesLabel(count: number): string {
    return count === 1 ? '1 factura' : `${count} facturas`;
}

function documentHref(row: DocumentRow): string {
    return `/${DOCUMENT_SEGMENTS[row.type]}/${row.id}`;
}

/**
 * Clientes: resumen de lo pendiente, tabla con lo que debe cada uno y, al lado,
 * el cliente seleccionado con su actividad. Debajo, quién más factura este año
 * y qué está pendiente de cobro.
 */
export default function CustomerIndex({ customers, filters, counts, stats, top_customers, pending_documents, selected }: Props) {
    const [search, setSearch] = useDebouncedSearch(filters.q ?? '', (q) =>
        router.get(CUSTOMERS_URL, { ...(q ? { q } : {}), ...(filters.archived ? { archived: 1 } : {}) }, { preserveState: true, preserveScroll: true, replace: true }),
    );

    /** En pantalla ancha, un clic muestra el resumen al lado; en el resto, abre la ficha. */
    function openCustomer(id: string) {
        if (window.matchMedia(SIDE_PANEL_QUERY).matches) {
            router.reload({ only: ['selected'], data: { selected: id }, replace: true });

            return;
        }

        router.visit(`${CUSTOMERS_URL}/${id}`);
    }

    return (
        <>
            <Head title="Clientes" />

            <PageHeader
                title="Clientes"
                description={customersLabel(counts.active)}
                actions={
                    <Button asChild>
                        <Link href={`${CUSTOMERS_URL}/create`}>
                            <Plus aria-hidden />
                            Nuevo cliente
                        </Link>
                    </Button>
                }
            />

            <section aria-label="Resumen" className="mb-4 grid gap-3 sm:grid-cols-3">
                <StatCard label="Clientes activos" value={String(counts.active)} tone="info" hint={`${counts.archived} archivados`} />
                <StatCard label="Pendiente de cobro" value={stats.pending_total_formatted} tone="warning" hint={invoicesLabel(stats.pending_count)} />
                <StatCard label="Vencido" value={stats.overdue_total_formatted} tone="danger" hint={invoicesLabel(stats.overdue_count)} />
            </section>

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="relative w-full max-w-sm">
                    <Search aria-hidden className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        aria-label="Buscar clientes"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Nombre, NIF o email"
                        className="bg-card pl-8"
                    />
                </div>
                <ArchivedTabs url={CUSTOMERS_URL} archived={filters.archived} counts={counts} />
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] 2xl:grid-cols-[minmax(0,1fr)_26rem]">
                <div className="min-w-0">
                    {customers.data.length === 0 ? (
                        <EmptyState filters={filters} />
                    ) : (
                        <div className="overflow-hidden rounded-xl border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <HeadCell>Cliente</HeadCell>
                                        <HeadCell className="hidden md:table-cell">NIF</HeadCell>
                                        <HeadCell className="hidden lg:table-cell">Email</HeadCell>
                                        <HeadCell className="hidden 2xl:table-cell">Teléfono</HeadCell>
                                        <HeadCell className="hidden sm:table-cell">Tipo</HeadCell>
                                        <HeadCell className="text-right">Sin cobrar</HeadCell>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {customers.data.map((customer) => (
                                        <TableRow
                                            key={customer.id}
                                            aria-selected={selected?.id === customer.id}
                                            className={cn('cursor-pointer', selected?.id === customer.id && 'bg-accent hover:bg-accent')}
                                            onClick={() => openCustomer(customer.id)}
                                        >
                                            <TableCell className="px-3 py-3 sm:px-4">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <CustomerAvatar name={customer.display_name} />
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={`${CUSTOMERS_URL}/${customer.id}`}
                                                            className="block truncate font-medium hover:underline"
                                                            onClick={(event) => event.stopPropagation()}
                                                        >
                                                            {customer.display_name}
                                                        </Link>
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {[customer.trade_name ? customer.legal_name : null, customer.city].filter(Boolean).join(' · ') ||
                                                                customer.tax_id ||
                                                                '—'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="hidden px-3 py-3 text-muted-foreground tabular-nums sm:px-4 md:table-cell">
                                                {customer.tax_id ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden max-w-56 truncate px-3 py-3 text-muted-foreground sm:px-4 lg:table-cell">
                                                {customer.email ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden px-3 py-3 text-muted-foreground tabular-nums sm:px-4 2xl:table-cell">
                                                {customer.phone ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden px-3 py-3 sm:table-cell sm:px-4">
                                                <StatusPill
                                                    tone={customer.kind === 'business' ? 'info' : 'neutral'}
                                                    label={customer.kind === 'business' ? 'Empresa' : 'Particular'}
                                                />
                                            </TableCell>
                                            <TableCell className="px-3 py-3 text-right sm:px-4">
                                                <UnpaidCell customer={customer} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <Pagination page={customers} />
                </div>

                <aside aria-label="Cliente seleccionado" className="hidden xl:sticky xl:top-6 xl:block">
                    {selected ? <SelectedPanel customer={selected} /> : <SelectHint />}
                </aside>
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                <Card title="Top clientes por facturación" subtitle="Facturas emitidas este año">
                    {top_customers.length === 0 ? (
                        <Empty>Aún no hay facturas emitidas este año.</Empty>
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {top_customers.map((customer, index) => (
                                <li key={customer.id}>
                                    <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                        <Link href={`${CUSTOMERS_URL}/${customer.id}`} className="min-w-0 truncate font-medium hover:underline">
                                            <span className="mr-2 text-muted-foreground tabular-nums">{index + 1}.</span>
                                            {customer.display_name}
                                        </Link>
                                        <span className="shrink-0 font-semibold tabular-nums">{customer.billed_total_formatted}</span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div className="h-full rounded-full bg-primary/70" style={{ width: `${customer.share_percent}%` }} />
                                        </div>
                                        <span className="w-20 shrink-0 text-right text-xs text-muted-foreground">{invoicesLabel(customer.invoices)}</span>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </Card>

                <Card
                    title="Documentos pendientes"
                    subtitle="Facturas emitidas sin cobrar, la que vence antes primero"
                    action={
                        <Link href="/invoices?payment_status=overdue" className="flex items-center gap-1 text-sm text-primary hover:underline">
                            Ver vencidas <ArrowRight className="size-4" aria-hidden />
                        </Link>
                    }
                >
                    {pending_documents.length === 0 ? (
                        <Empty>No hay nada pendiente de cobro.</Empty>
                    ) : (
                        <DocumentList rows={pending_documents} detail={(row) => (row.due_date ? `Vence el ${formatDate(row.due_date)}` : 'Sin vencimiento')} />
                    )}
                </Card>
            </div>
        </>
    );
}

/** Lo que debe el cliente; en rojo si parte está vencida. */
function UnpaidCell({ customer }: { customer: CustomerIndexRow }) {
    if (customer.unpaid_total === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="inline-flex flex-col items-end">
            <span className={cn('font-semibold tabular-nums', customer.overdue_total > 0 && 'text-danger-strong')}>{customer.unpaid_total_formatted}</span>
            {customer.overdue_total > 0 ? <span className="text-xs text-danger">{customer.overdue_total_formatted} vencido</span> : null}
        </span>
    );
}

function SelectedPanel({ customer }: { customer: SelectedCustomer }) {
    const url = `${CUSTOMERS_URL}/${customer.id}`;

    return (
        <div className="rounded-xl border bg-card">
            <div className="border-b p-4">
                <div className="flex items-start gap-3">
                    <CustomerAvatar name={customer.display_name} className="size-11 text-base" />
                    <div className="min-w-0 flex-1">
                        <p className="font-semibold break-words">{customer.display_name}</p>
                        <div className="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <StatusPill
                                tone={customer.kind === 'business' ? 'info' : 'neutral'}
                                label={customer.kind === 'business' ? 'Empresa' : 'Particular'}
                            />
                            <span className="tabular-nums">{customer.tax_id ?? 'Sin NIF'}</span>
                        </div>
                    </div>
                </div>
                <div className="mt-3 flex gap-2">
                    <Button size="sm" variant="outline" className="flex-1" asChild>
                        <Link href={url}>Ver ficha</Link>
                    </Button>
                    <Button size="sm" className="flex-1" asChild>
                        <Link href={`/invoices/create?customer_id=${customer.id}`}>
                            <FilePlus aria-hidden />
                            Nueva factura
                        </Link>
                    </Button>
                </div>
            </div>

            <dl className="grid grid-cols-3 divide-x border-b text-center">
                <Figure label="Este año" value={customer.billed_year_total_formatted} />
                <Figure label="Sin cobrar" value={customer.unpaid_total_formatted} />
                <Figure label="Vencido" value={customer.overdue_total_formatted} danger={customer.overdue_total > 0} />
            </dl>

            {customer.email || customer.phone ? (
                <div className="flex flex-col gap-1.5 border-b p-4 text-sm">
                    {customer.email ? (
                        <a href={`mailto:${customer.email}`} className="flex items-center gap-2 truncate text-primary hover:underline">
                            <Mail aria-hidden className="size-4 shrink-0" />
                            {customer.email}
                        </a>
                    ) : null}
                    {customer.phone ? (
                        <a href={`tel:${customer.phone}`} className="flex items-center gap-2 text-primary hover:underline">
                            <Phone aria-hidden className="size-4 shrink-0" />
                            {customer.phone}
                        </a>
                    ) : null}
                </div>
            ) : null}

            {customer.notes ? (
                <div className="border-b p-4">
                    <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">Notas</p>
                    <p className="line-clamp-4 text-sm whitespace-pre-line">{customer.notes}</p>
                </div>
            ) : null}

            <div className="p-4">
                <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">Actividad reciente</p>
                {customer.recent_documents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Sin documentos todavía.</p>
                ) : (
                    <DocumentList rows={customer.recent_documents} compact detail={(row) => formatDate(row.issue_date) || 'Borrador'} />
                )}
            </div>
        </div>
    );
}

function SelectHint() {
    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
            <MousePointerClick aria-hidden className="size-6" />
            Selecciona un cliente para ver su resumen y su actividad.
        </div>
    );
}

function Figure({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
    return (
        <div className="px-2 py-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={cn('text-sm font-semibold tabular-nums', danger && 'text-danger-strong')}>{value}</dd>
        </div>
    );
}

function DocumentList({ rows, detail, compact }: { rows: DocumentRow[]; detail: (row: DocumentRow) => string; compact?: boolean }) {
    return (
        <ul className="flex flex-col gap-1.5">
            {rows.map((row) => (
                <li key={row.id}>
                    <Link
                        href={documentHref(row)}
                        className="flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-muted"
                    >
                        <span className="min-w-0">
                            <span className="block truncate font-medium">
                                {compact ? (row.full_number ?? (row.status === 'draft' ? 'Borrador' : 'Sin número')) : (row.customer_name ?? 'Sin cliente')}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {!compact && row.full_number ? `${row.full_number} · ` : ''}
                                {detail(row)}
                            </span>
                        </span>
                        <span className="flex shrink-0 flex-col items-end gap-1">
                            <span className="font-semibold tabular-nums">{row.total_formatted}</span>
                            <RowStatus status={row.status} statusLabel={row.status_label} paymentStatus={row.payment_status} paymentStatusLabel={row.payment_status_label} />
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function Card({ title, subtitle, action, children }: { title: string; subtitle: string; action?: ReactNode; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-4">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="py-6 text-center text-sm text-muted-foreground">{children}</p>;
}

function EmptyState({ filters }: { filters: CatalogFilters }) {
    let message = 'Todavía no tienes clientes. Crea el primero para poder facturarle.';

    if (filters.q) {
        message = 'Ningún cliente coincide con la búsqueda.';
    } else if (filters.archived) {
        message = 'No hay clientes archivados.';
    }

    return <p className="rounded-xl border border-dashed bg-card p-10 text-center text-muted-foreground">{message}</p>;
}

function HeadCell({ className, children }: { className?: string; children: string }) {
    return <TableHead className={cn('h-10 px-3 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:px-4', className)}>{children}</TableHead>;
}
