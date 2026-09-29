import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { PaymentStatusBadge, StatusBadge } from '@/components/documents/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate } from '@/lib/dates';
import { DOCUMENT_LIST_TITLES, NEW_DOCUMENT_LABELS, documentUrl } from '@/lib/documents';
import type { DocumentFilters, DocumentRow, DocumentTotals, DocumentTypeProps, Paginated, SeriesOption } from '@/types/documents';

interface Option {
    value: string;
    label: string;
}

interface Props {
    type: DocumentTypeProps;
    documents: Paginated<DocumentRow>;
    filters: DocumentFilters;
    totals: DocumentTotals;
    series: SeriesOption[];
    options: { statuses: Option[]; payment_statuses: Option[] };
}

/** Espera tras la última tecla antes de buscar, para no lanzar una petición por letra. */
const SEARCH_DEBOUNCE_MS = 300;

/** Valor de los desplegables para "sin filtro" (Radix no admite valores vacíos). */
const ALL = 'all';

type FilterKey = keyof DocumentFilters;

export default function DocumentIndex({ type, documents, filters, totals, series, options }: Props) {
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

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">{DOCUMENT_LIST_TITLES[type.value]}</h1>
                {newLabel ? (
                    <Button asChild>
                        <Link href={`${documentUrl(type)}/create`}>
                            <Plus aria-hidden />
                            {newLabel}
                        </Link>
                    </Button>
                ) : null}
            </div>

            <section aria-label="Totales" className="mb-6 grid gap-3 sm:grid-cols-3">
                <TotalCard title="Documentos" value={String(totals.count)} description={hasFilters ? 'Con los filtros aplicados' : 'En total'} />
                <TotalCard title="Importe emitido" value={totals.sum_total_formatted} description="Sin contar borradores" />
                <TotalCard title="Pendiente de cobro" value={totals.sum_pending_formatted} description="Emitidas sin marcar como cobradas" />
            </section>

            <section aria-label="Filtros" className="mb-4 grid gap-3 md:grid-cols-6">
                <div className="md:col-span-2">
                    <Label htmlFor="q" className="mb-1.5">
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
                            className="pl-8"
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
                    <Label htmlFor="from" className="mb-1.5">
                        Desde
                    </Label>
                    <Input id="from" type="date" value={filters.from ?? ''} onChange={(event) => applyFilters({ from: event.target.value })} />
                </div>
                <div>
                    <Label htmlFor="to" className="mb-1.5">
                        Hasta
                    </Label>
                    <Input id="to" type="date" value={filters.to ?? ''} onChange={(event) => applyFilters({ to: event.target.value })} />
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
                <p className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                    {hasFilters ? 'Ningún documento coincide con los filtros.' : 'Todavía no hay documentos.'}
                </p>
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Número</TableHead>
                                <TableHead className="hidden md:table-cell">Fecha</TableHead>
                                <TableHead>Cliente</TableHead>
                                <TableHead className="hidden md:table-cell">Vencimiento</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((row) => (
                                <TableRow key={row.id} className="cursor-pointer" onClick={() => router.visit(documentUrl(type, row.id))}>
                                    <TableCell className="font-medium">
                                        <Link href={documentUrl(type, row.id)} className="hover:underline" onClick={(event) => event.stopPropagation()}>
                                            {row.full_number ?? 'Borrador'}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">{formatDate(row.issue_date)}</TableCell>
                                    <TableCell className="max-w-40 truncate md:max-w-64">{row.customer_name ?? '—'}</TableCell>
                                    <TableCell className="hidden md:table-cell">{formatDate(row.due_date)}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            <StatusBadge status={row.status} label={row.status_label} />
                                            {row.payment_status && row.payment_status_label ? (
                                                <PaymentStatusBadge status={row.payment_status} label={row.payment_status_label} />
                                            ) : null}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">{row.total_formatted}</TableCell>
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
        </>
    );
}

function TotalCard({ title, value, description }: { title: string; value: string; description: string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">{description}</CardContent>
        </Card>
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
            <Label htmlFor={id} className="mb-1.5">
                {label}
            </Label>
            <Select value={value ?? ALL} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
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
