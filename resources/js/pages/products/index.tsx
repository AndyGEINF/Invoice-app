import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Plus, Search } from 'lucide-react';

import { ArchivedTabs } from '@/components/ArchivedTabs';
import { PageHeader } from '@/components/PageHeader';
import { Pagination } from '@/components/Pagination';
import { StatusPill } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { decimalForInput } from '@/lib/decimals';
import { useDebouncedSearch } from '@/lib/use-debounced-search';
import { cn } from '@/lib/utils';
import type { CatalogCounts, CatalogFilters, ProductRow } from '@/types/catalog';
import type { Paginated } from '@/types/documents';

interface Props {
    products: Paginated<ProductRow>;
    filters: CatalogFilters;
    counts: CatalogCounts;
}

const PRODUCTS_URL = '/products';

function productsLabel(count: number): string {
    return count === 1 ? '1 producto o servicio' : `${count} productos y servicios`;
}

/** Catálogo: lo que vendes, con su precio e IVA. Añadirlo a una factura copia sus datos. */
export default function ProductIndex({ products, filters, counts }: Props) {
    const [search, setSearch] = useDebouncedSearch(filters.q ?? '', (q) =>
        router.get(PRODUCTS_URL, { ...(q ? { q } : {}), ...(filters.archived ? { archived: 1 } : {}) }, { preserveState: true, preserveScroll: true, replace: true }),
    );

    function toggleArchived(product: ProductRow) {
        router.post(`${PRODUCTS_URL}/${product.id}/${product.is_archived ? 'restore' : 'archive'}`, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Catálogo" />

            <PageHeader
                title="Catálogo"
                description={productsLabel(counts.active)}
                actions={
                    <Button asChild>
                        <Link href={`${PRODUCTS_URL}/create`}>
                            <Plus aria-hidden />
                            Nuevo producto
                        </Link>
                    </Button>
                }
            />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="relative w-full max-w-sm">
                    <Search aria-hidden className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        aria-label="Buscar en el catálogo"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Nombre, referencia o descripción"
                        className="bg-card pl-8"
                    />
                </div>
                <ArchivedTabs url={PRODUCTS_URL} archived={filters.archived} counts={counts} />
            </div>

            {products.data.length === 0 ? (
                <p className="rounded-xl border border-dashed bg-card p-10 text-center text-muted-foreground">
                    {filters.q
                        ? 'Nada del catálogo coincide con la búsqueda.'
                        : filters.archived
                          ? 'No hay productos archivados.'
                          : 'El catálogo está vacío. Añade lo que vendes para rellenar las facturas más rápido.'}
                </p>
            ) : (
                <div className="overflow-hidden rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <HeadCell>Nombre</HeadCell>
                                <HeadCell className="hidden md:table-cell">Referencia</HeadCell>
                                <HeadCell className="hidden sm:table-cell">Tipo</HeadCell>
                                <HeadCell className="text-right">Precio</HeadCell>
                                <HeadCell className="hidden text-right sm:table-cell">IVA</HeadCell>
                                <HeadCell className="w-12">
                                    <span className="sr-only">Acciones</span>
                                </HeadCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.map((product) => (
                                <TableRow key={product.id} className="cursor-pointer" onClick={() => router.visit(`${PRODUCTS_URL}/${product.id}/edit`)}>
                                    <TableCell className="max-w-0 px-3 py-3 sm:px-4">
                                        <Link
                                            href={`${PRODUCTS_URL}/${product.id}/edit`}
                                            className="block truncate font-medium hover:underline"
                                            onClick={(event) => event.stopPropagation()}
                                        >
                                            {product.name}
                                        </Link>
                                        {product.description ? <span className="block truncate text-xs text-muted-foreground">{product.description}</span> : null}
                                    </TableCell>
                                    <TableCell className="hidden px-3 py-3 text-muted-foreground sm:px-4 md:table-cell">{product.sku ?? '—'}</TableCell>
                                    <TableCell className="hidden px-3 py-3 sm:table-cell sm:px-4">
                                        <StatusPill tone="neutral" label={product.type_label} />
                                    </TableCell>
                                    <TableCell className="px-3 py-3 text-right whitespace-nowrap sm:px-4">
                                        <span className="font-semibold tabular-nums">{product.unit_price_formatted}</span>
                                        <span className="text-xs text-muted-foreground"> / {product.unit}</span>
                                    </TableCell>
                                    <TableCell className="hidden px-3 py-3 text-right text-muted-foreground tabular-nums sm:table-cell sm:px-4">
                                        {product.exemption_code ? `Exento (${product.exemption_code})` : `${decimalForInput(product.vat_rate)} %`}
                                    </TableCell>
                                    <TableCell className="px-2 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-muted-foreground"
                                            aria-label={product.is_archived ? `Restaurar ${product.name}` : `Archivar ${product.name}`}
                                            title={product.is_archived ? 'Restaurar' : 'Archivar'}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                toggleArchived(product);
                                            }}
                                        >
                                            {product.is_archived ? <ArchiveRestore aria-hidden /> : <Archive aria-hidden />}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <Pagination page={products} />
        </>
    );
}

function HeadCell({ className, children }: { className?: string; children: React.ReactNode }) {
    return <TableHead className={cn('h-10 px-3 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:px-4', className)}>{children}</TableHead>;
}
