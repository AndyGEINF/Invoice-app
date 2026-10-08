import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, FilePlus, Pencil, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';

import { CustomerAvatar } from '@/components/customers/CustomerAvatar';
import { RowStatus } from '@/components/documents/StatusBadge';
import { StatusPill } from '@/components/StatusPill';
import type { Tone } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/dates';
import { decimalForInput } from '@/lib/decimals';
import { DOCUMENT_SEGMENTS } from '@/lib/documents';
import type { CustomerShowProps, ViesStatus } from '@/types/catalog';

const CUSTOMERS_URL = '/customers';

const VIES_LABELS: Record<Exclude<ViesStatus, 'not_applicable'>, { tone: Tone; label: string }> = {
    validated: { tone: 'success', label: 'Validado en VIES' },
    pending: { tone: 'warning', label: 'Sin validar en VIES' },
};

const DOCUMENT_TYPE_LABELS: Record<string, string> = {
    quote: 'Presupuesto',
    invoice: 'Factura',
    credit_note: 'Rectificativa',
};

/** Ficha del cliente: datos de facturación, contactos, notas y sus documentos. */
export default function CustomerShow({ customer, documents, vies }: CustomerShowProps) {
    const url = `${CUSTOMERS_URL}/${customer.id}`;

    return (
        <>
            <Head title={customer.display_name} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <CustomerAvatar name={customer.display_name} className="size-12 text-lg" />
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold tracking-tight">{customer.display_name}</h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <StatusPill tone={customer.kind === 'business' ? 'info' : 'neutral'} label={customer.kind_label} />
                            {customer.is_archived ? <StatusPill tone="neutral" label="Archivado" /> : null}
                            {customer.trade_name ? <span className="truncate">{customer.legal_name}</span> : null}
                        </div>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {customer.is_archived ? (
                        <Button variant="outline" onClick={() => router.post(`${url}/restore`, {}, { preserveScroll: true })}>
                            <ArchiveRestore aria-hidden />
                            Restaurar
                        </Button>
                    ) : (
                        <>
                            <Button variant="outline" onClick={() => router.post(`${url}/archive`, {}, { preserveScroll: true })}>
                                <Archive aria-hidden />
                                Archivar
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`${url}/edit`}>
                                    <Pencil aria-hidden />
                                    Editar
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={`/invoices/create?customer_id=${customer.id}`}>
                                    <FilePlus aria-hidden />
                                    Nueva factura
                                </Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section aria-label="Documentos" className="rounded-xl border bg-card">
                    <h2 className="border-b px-4 py-3 font-semibold">Documentos</h2>
                    {documents.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">Todavía no hay documentos para este cliente.</p>
                    ) : (
                        <ul>
                            {documents.map((row) => (
                                <li key={row.id} className="border-b last:border-b-0">
                                    <Link
                                        href={`/${DOCUMENT_SEGMENTS[row.type]}/${row.id}`}
                                        className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-medium text-primary">
                                                {row.full_number ?? (row.status === 'draft' ? 'Borrador' : 'Sin número')}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {DOCUMENT_TYPE_LABELS[row.type]}
                                                {row.issue_date ? ` · ${formatDate(row.issue_date)}` : ''}
                                            </span>
                                        </span>
                                        <span className="text-right font-semibold tabular-nums">{row.total_formatted}</span>
                                        <span className="hidden w-24 text-right sm:block">
                                            <RowStatus
                                                status={row.status}
                                                statusLabel={row.status_label}
                                                paymentStatus={row.payment_status}
                                                paymentStatusLabel={row.payment_status_label}
                                            />
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <aside className="flex flex-col gap-4">
                    <Panel title="Datos de facturación">
                        <dl className="flex flex-col gap-2 text-sm">
                            <Row label={customer.tax_id_type_label ?? 'NIF'}>{customer.tax_id || 'Sin NIF (solo factura simplificada)'}</Row>
                            <Row label="Dirección">{customer.address_line ?? '—'}</Row>
                            <Row label="Email">{customer.contacts.find((contact) => contact.is_default)?.email || customer.email || '—'}</Row>
                            <Row label="Plazo de pago">{customer.payment_terms_days === 0 ? 'Al contado' : `${customer.payment_terms_days} días`}</Row>
                            {customer.default_vat_rate ? <Row label="IVA por defecto">{decimalForInput(customer.default_vat_rate)} %</Row> : null}
                            {customer.irpf_applies && customer.kind === 'business' ? <Row label="Retención">Se aplica IRPF</Row> : null}
                            {customer.surcharge_applies ? <Row label="Recargo">Recargo de equivalencia</Row> : null}
                        </dl>
                    </Panel>

                    {vies.status !== 'not_applicable' ? (
                        <Panel title="IVA intracomunitario">
                            <StatusPill tone={VIES_LABELS[vies.status].tone} label={VIES_LABELS[vies.status].label} />
                            <p className="mt-2 text-sm text-muted-foreground">
                                {vies.validated_at
                                    ? `Comprobado el ${formatDateTime(vies.validated_at)}.`
                                    : 'Se comprueba solo al guardar. Si VIES no responde, se reintenta más tarde; no impide facturar.'}
                            </p>
                            <Button variant="outline" size="sm" className="mt-3" onClick={() => router.post(`${url}/validate-vat`, {}, { preserveScroll: true })}>
                                <ShieldCheck aria-hidden />
                                Comprobar ahora
                            </Button>
                        </Panel>
                    ) : null}

                    <Panel title="Contactos">
                        {customer.contacts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Sin contactos.</p>
                        ) : (
                            <ul className="flex flex-col gap-3 text-sm">
                                {customer.contacts.map((contact) => (
                                    <li key={contact.email}>
                                        <p className="font-medium">
                                            {contact.name}
                                            {contact.is_default ? <span className="ml-2 text-xs font-normal text-muted-foreground">Principal</span> : null}
                                        </p>
                                        <p className="text-muted-foreground">{[contact.email, contact.phone].filter(Boolean).join(' · ')}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <Panel title="Notas">
                        <p className="text-sm whitespace-pre-line text-muted-foreground">{customer.notes || 'Sin notas.'}</p>
                    </Panel>
                </aside>
            </div>
        </>
    );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card p-4">
            <h2 className="mb-3 text-sm font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-right break-words">{children}</dd>
        </div>
    );
}
