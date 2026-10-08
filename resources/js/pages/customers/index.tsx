import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';

import { ArchivedTabs } from '@/components/ArchivedTabs';
import { CustomerAvatar } from '@/components/customers/CustomerAvatar';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { StatusPill } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDebouncedSearch } from '@/lib/use-debounced-search';
import { cn } from '@/lib/utils';
import type { CatalogCounts, CatalogFilters, CustomerRow } from '@/types/catalog';
import type { Paginated } from '@/types/documents';

interface Props {
    customers: Paginated<CustomerRow>;
    filters: CatalogFilters;
    counts: CatalogCounts;
}

const CUSTOMERS_URL = '/customers';

function customersLabel(count: number): string {
    return count === 1 ? '1 cliente' : `${count} clientes`;
}

/** Listado de clientes: búsqueda por nombre, NIF o email y pestañas activos / archivados. */
export default function CustomerIndex({ customers, filters, counts }: Props) {
    const [search, setSearch] = useDebouncedSearch(filters.q ?? '', (q) =>
        router.get(CUSTOMERS_URL, { ...(q ? { q } : {}), ...(filters.archived ? { archived: 1 } : {}) }, { preserveState: true, preserveScroll: true, replace: true }),
    );

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
                                <HeadCell className="hidden xl:table-cell">Teléfono</HeadCell>
                                <HeadCell className="hidden sm:table-cell">Tipo</HeadCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.map((customer) => (
                                <TableRow key={customer.id} className="cursor-pointer" onClick={() => router.visit(`${CUSTOMERS_URL}/${customer.id}`)}>
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
                                    <TableCell className="hidden px-3 py-3 text-muted-foreground tabular-nums sm:px-4 md:table-cell">{customer.tax_id ?? '—'}</TableCell>
                                    <TableCell className="hidden max-w-60 truncate px-3 py-3 text-muted-foreground sm:px-4 lg:table-cell">{customer.email ?? '—'}</TableCell>
                                    <TableCell className="hidden px-3 py-3 text-muted-foreground tabular-nums sm:px-4 xl:table-cell">{customer.phone ?? '—'}</TableCell>
                                    <TableCell className="hidden px-3 py-3 sm:table-cell sm:px-4">
                                        <StatusPill tone={customer.kind === 'business' ? 'info' : 'neutral'} label={customer.kind === 'business' ? 'Empresa' : 'Particular'} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <Pagination page={customers} />
        </>
    );
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
