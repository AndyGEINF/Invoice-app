import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Clock, PencilLine, Plus, Search } from 'lucide-react';
import type { ComponentType } from 'react';
import { useEffect, useRef, useState } from 'react';

import { RowStatus } from '@/components/documents/StatusBadge';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import type { Tone } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate } from '@/lib/dates';
import { DOCUMENT_LIST_TITLES, NEW_DOCUMENT_LABELS, countLabel, documentUrl } from '@/lib/documents';
import { cn } from '@/lib/utils';
import type {
    AlertGroup,
    DocumentAlerts,
    DocumentFilters,
    DocumentRow,
    DocumentTotals,
    DocumentTypeProps,
    Paginated,
    SeriesOption,
} from '@/types/documents';

interface Option {
    value: string;
    label: string;
}

interface Props {
    type: DocumentTypeProps;
    documents: Paginated<DocumentRow>;
    filters: DocumentFilters;
    totals: DocumentTotals;
    alerts: DocumentAlerts;
    series: SeriesOption[];
    options: { statuses: Option[]; payment_statuses: Option[] };
}

/** Espera tras la última tecla antes de buscar, para no lanzar una petición por letra. */
const SEARCH_DEBOUNCE_MS = 300;

/** Valor de los desplegables para "sin filtro" (Radix no admite valores vacíos). */
const ALL = 'all';

type FilterKey = keyof DocumentFilters;

export default function DocumentIndex({ type, documents, filters, totals, alerts, series, options }: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const firstRender = useRef(true);
    const newLabel = NEW_DOCUMENT_LABELS[type.value];

    function applyFilters(changes: Partial<Record<FilterKey, string | null>>) {
        const next = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(([, value]) => value !== null && value !== '' && value !== ALL),
        );

        router.get(documentUrl(type), next, { preserveState: true, preserveScroll: true, replace: true });
    }

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => applyFilters({ q: search }), SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // Solo reacciona a lo que se escribe; el resto de filtros van en `applyFilters`.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasFilters = Object.values(filters).some((value) => value !== null);

    return (
        <>
            <Head title={DOCUMENT_LIST_TITLES[type.value]} />

            <PageHeader
                title={DOCUMENT_LIST_TITLES[type.value]}
                description={hasFilters ? `${countLabel(type.value, totals.count)} con estos filtros` : countLabel(type.value, totals.count)}
                actions={
                    newLabel ? (
                        <Button asChild>
                            <Link href={`${documentUrl(type)}/create`}>
                                <Plus aria-hidden />
                                {newLabel}
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <section aria-label="Totales" className="mb-4 grid gap-3 sm:grid-cols-3">
                <StatCard label="Importe emitido" value={totals.sum_total_formatted} tone="info" />
                <StatCard label="Pendiente de cobro" value={totals.sum_pending_formatted} tone="warning" />
                <StatCard label="Vencido" value={totals.sum_overdue_formatted} tone="danger" />
            </section>

            <section aria-label="Filtros" className="mb-4 grid gap-3 md:grid-cols-6">
                <div className="md:col-span-2">
                    <Label htmlFor="q" className="mb-1.5 text-xs text-muted-foreground">
                        Buscar
                    </Label>
                    <div className="relative">
                        <Search aria-hidden className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="q"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Número, cliente o NIF"
                            className="bg-card pl-8"
                        />
                    </div>
                </div>

                <FilterSelect
                    id="status"
                    label="Estado"
                    value={filters.status}
                    options={options.statuses}
                    onChange={(value) => applyFilters({ status: value })}
                />
                <FilterSelect
                    id="payment_status"
                    label="Cobro"
                    value={filters.payment_status}
                    options={options.payment_statuses}
                    onChange={(value) => applyFilters({ payment_status: value })}
                />

                <div>
                    <Label htmlFor="from" className="mb-1.5 text-xs text-muted-foreground">
                        Desde
                    </Label>
                    <Input
                        id="from"
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(event) => applyFilters({ from: event.target.value })}
                        className="bg-card"
                    />
                </div>
                <div>
                    <Label htmlFor="to" className="mb-1.5 text-xs text-muted-foreground">
                        Hasta
                    </Label>
                    <Input id="to" type="date" value={filters.to ?? ''} onChange={(event) => applyFilters({ to: event.target.value })} className="bg-card" />
                </div>

                {series.length > 1 ? (
                    <FilterSelect
                        id="series_id"
                        label="Serie"
                        value={filters.series_id}
                        options={series.map((item) => ({ value: item.id, label: item.code }))}
                        onChange={(value) => applyFilters({ series_id: value })}
                    />
                ) : null}
            </section>

            {documents.data.length === 0 ? (
                <p className="rounded-xl border border-dashed bg-card p-10 text-center text-muted-foreground">
                    {hasFilters ? 'Ningún documento coincide con los filtros.' : 'Todavía no hay documentos.'}
                </p>
            ) : (
                <div className="overflow-hidden rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <HeadCell>Nº</HeadCell>
                                <HeadCell className="hidden sm:table-cell">Cliente</HeadCell>
                                <HeadCell className="text-right">Importe</HeadCell>
                                <HeadCell className="hidden text-right sm:table-cell">Fecha</HeadCell>
                                <HeadCell className="hidden text-right lg:table-cell">Vencimiento</HeadCell>
                                <HeadCell className="hidden text-right sm:table-cell">Estado</HeadCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((row) => (
                                <TableRow key={row.id} className="cursor-pointer" onClick={() => router.visit(documentUrl(type, row.id))}>
                                    <TableCell className="px-3 py-3 sm:px-4">
                                        <Link
                                            href={documentUrl(type, row.id)}
                                            className="font-medium text-primary hover:underline"
                                            onClick={(event) => event.stopPropagation()}
                                        >
                                            {row.full_number ?? 'Borrador'}
                                        </Link>
                                        {/* En móvil, dos columnas: número y cliente | importe y estado. */}
                                        <span className="block max-w-44 truncate text-xs text-muted-foreground sm:hidden">
                                            {row.customer_name ?? 'Sin cliente'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="hidden max-w-44 truncate px-3 py-3 sm:px-4 sm:table-cell md:max-w-72">
                                        {row.customer_name ?? 'Sin cliente'}
                                    </TableCell>
                                    <TableCell className="px-3 py-3 text-right sm:px-4">
                                        <span className="font-semibold tabular-nums">{row.total_formatted}</span>
                                        <span className="mt-1 block sm:hidden">
                                            <RowStatus
                                                status={row.status}
                                                statusLabel={row.status_label}
                                                paymentStatus={row.payment_status}
                                                paymentStatusLabel={row.payment_status_label}
                                            />
                                        </span>
                                    </TableCell>
                                    <TableCell className="hidden px-3 py-3 sm:px-4 text-right text-muted-foreground tabular-nums sm:table-cell">
                                        {formatDate(row.issue_date) || '—'}
                                    </TableCell>
                                    <TableCell className="hidden px-3 py-3 sm:px-4 text-right text-muted-foreground tabular-nums lg:table-cell">
                                        {formatDate(row.due_date) || '—'}
                                    </TableCell>
                                    <TableCell className="hidden px-3 py-3 text-right sm:table-cell sm:px-4">
                                        <RowStatus
                                            status={row.status}
                                            statusLabel={row.status_label}
                                            paymentStatus={row.payment_status}
                                            paymentStatusLabel={row.payment_status_label}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            {documents.last_page > 1 ? (
                <nav aria-label="Paginación" className="mt-4 flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                        {documents.from}–{documents.to} de {documents.total}
                    </span>
                    <div className="flex gap-2">
                        <PageLink href={documents.prev_page_url} label="Anterior" icon="prev" />
                        <PageLink href={documents.next_page_url} label="Siguiente" icon="next" />
                    </div>
                </nav>
            ) : null}

            {alerts.overdue.count > 0 || alerts.drafts.count > 0 ? (
                <section aria-label="Avisos" className="mt-6 grid gap-4 md:grid-cols-2">
                    {alerts.overdue.count > 0 ? (
                        <AlertCard
                            tone="danger"
                            icon={Clock}
                            title={countLabel(type.value, alerts.overdue.count, alerts.overdue.count === 1 ? 'vencida' : 'vencidas')}
                            subtitle={`Valor: ${alerts.overdue.sum_formatted}, pendiente de cobro`}
                            group={alerts.overdue}
                            type={type}
                            detail={(row) => `Venció el ${formatDate(row.due_date)}`}
                        />
                    ) : null}
                    {alerts.drafts.count > 0 ? (
                        <AlertCard
                            tone="info"
                            icon={PencilLine}
                            title={`${alerts.drafts.count} ${alerts.drafts.count === 1 ? 'borrador' : 'borradores'} sin emitir`}
                            subtitle={`Valor: ${alerts.drafts.sum_formatted}`}
                            group={alerts.drafts}
                            type={type}
                            detail={() => 'Borrador'}
                        />
                    ) : null}
                </section>
            ) : null}
        </>
    );
}

function HeadCell({ className, children }: { className?: string; children: string }) {
    return <TableHead className={cn('h-10 px-3 text-xs sm:px-4 font-medium tracking-wide text-muted-foreground uppercase', className)}>{children}</TableHead>;
}

const ALERT_CLASSES: Partial<Record<Tone, { card: string; icon: string; title: string }>> = {
    danger: { card: 'border-danger/25 bg-danger-soft/60', icon: 'bg-danger-soft text-danger', title: 'text-danger-strong' },
    info: { card: 'border-info/25 bg-info-soft/60', icon: 'bg-info-soft text-info', title: 'text-info-strong' },
};

/** Tarjeta de aviso bajo la tabla: qué pide atención y los documentos más urgentes. */
function AlertCard({
    tone,
    icon: Icon,
    title,
    subtitle,
    group,
    type,
    detail,
}: {
    tone: 'danger' | 'info';
    icon: ComponentType<{ className?: string }>;
    title: string;
    subtitle: string;
    group: AlertGroup;
    type: DocumentTypeProps;
    detail: (row: DocumentRow) => string;
}) {
    const classes = ALERT_CLASSES[tone];

    return (
        <div className={cn('rounded-xl border p-4', classes?.card)}>
            <div className="mb-3 flex items-start gap-3">
                <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', classes?.icon)}>
                    <Icon className="size-4" />
                </span>
                <div>
                    <p className={cn('font-semibold', classes?.title)}>{title}</p>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                </div>
            </div>
            <ul className="flex flex-col gap-1.5">
                {group.items.map((row) => (
                    <li key={row.id}>
                        <Link
                            href={documentUrl(type, row.id)}
                            className="flex items-center justify-between gap-3 rounded-lg bg-card px-3 py-2 text-sm transition-colors hover:bg-muted"
                        >
                            <span className="min-w-0">
                                <span className="block truncate font-medium">{row.customer_name ?? 'Sin cliente'}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {row.full_number ? `${row.full_number} · ` : ''}
                                    {detail(row)}
                                </span>
                            </span>
                            <span className="shrink-0 font-semibold tabular-nums">{row.total_formatted}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function FilterSelect({
    id,
    label,
    value,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string | null;
    options: Option[];
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <Label htmlFor={id} className="mb-1.5 text-xs text-muted-foreground">
                {label}
            </Label>
            <Select value={value ?? ALL} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full bg-card">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
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
