import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, FilePlus, Pencil, Save, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { CustomerAvatar } from '@/components/customers/CustomerAvatar';
import { FieldError } from '@/components/documents/LineEditor';
import { RowStatus } from '@/components/documents/StatusBadge';
import { StatCard } from '@/components/StatCard';
import { StatusPill } from '@/components/StatusPill';
import type { Tone } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/dates';
import { decimalForInput } from '@/lib/decimals';
import { DOCUMENT_SEGMENTS } from '@/lib/documents';
import { TAB_TRIGGER_CLASS } from '@/lib/tabs';
import type { CustomerDetail, CustomerShowProps, ViesStatus } from '@/types/catalog';
import type { DocumentRow } from '@/types/documents';

const CUSTOMERS_URL = '/customers';

/** Documentos que se enseñan en la pestaña Resumen. */
const SUMMARY_ITEMS = 5;

const VIES_LABELS: Record<Exclude<ViesStatus, 'not_applicable'>, { tone: Tone; label: string }> = {
    validated: { tone: 'success', label: 'Validado en VIES' },
    pending: { tone: 'warning', label: 'Sin validar en VIES' },
};

const DOCUMENT_TYPE_LABELS: Record<string, string> = {
    quote: 'Presupuesto',
    invoice: 'Factura',
    credit_note: 'Rectificativa',
};

function invoicesLabel(count: number): string {
    return count === 1 ? '1 factura' : `${count} facturas`;
}

/**
 * Ficha del cliente: cifras, pestañas (Resumen, Facturas, Presupuestos, Datos
 * fiscales y contactos) y, a la derecha, sus notas.
 */
export default function CustomerShow({ customer, stats, invoices, quotes, vies }: CustomerShowProps) {
    const url = `${CUSTOMERS_URL}/${customer.id}`;
    const recent = [...invoices, ...quotes].slice(0, SUMMARY_ITEMS);

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
                            {customer.tax_id ? <span className="tabular-nums">{customer.tax_id}</span> : null}
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

            <section aria-label="Cifras" className="mb-6 grid gap-3 sm:grid-cols-3">
                <StatCard
                    label={`Facturado en ${stats.year}`}
                    value={stats.billed_year_total_formatted}
                    tone="info"
                    hint={invoicesLabel(stats.billed_year_count)}
                />
                <StatCard label="Sin cobrar" value={stats.unpaid_total_formatted} tone="warning" hint="Incluye lo vencido" />
                <StatCard label="Vencido" value={stats.overdue_total_formatted} tone="danger" />
            </section>

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] 2xl:grid-cols-[minmax(0,1fr)_26rem]">
                <Tabs defaultValue="summary" className="min-w-0">
                    <TabsList className="mb-4 h-auto flex-wrap">
                        <TabsTrigger value="summary" className={TAB_TRIGGER_CLASS}>
                            Resumen
                        </TabsTrigger>
                        <TabsTrigger value="invoices" className={TAB_TRIGGER_CLASS}>
                            Facturas <span className="ml-1 tabular-nums opacity-70">{invoices.length}</span>
                        </TabsTrigger>
                        <TabsTrigger value="quotes" className={TAB_TRIGGER_CLASS}>
                            Presupuestos <span className="ml-1 tabular-nums opacity-70">{quotes.length}</span>
                        </TabsTrigger>
                        <TabsTrigger value="details" className={TAB_TRIGGER_CLASS}>
                            Datos fiscales y contactos
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="summary" className="flex flex-col gap-4">
                        <Panel title="Actividad reciente">
                            {recent.length === 0 ? (
                                <Empty>Todavía no hay documentos para este cliente.</Empty>
                            ) : (
                                <DocumentTable rows={recent} showType />
                            )}
                        </Panel>
                        <div className="grid gap-4 md:grid-cols-2">
                            <BillingPanel customer={customer} />
                            <ContactsPanel customer={customer} />
                        </div>
                    </TabsContent>

                    <TabsContent value="invoices">
                        <Panel title="Facturas y rectificativas">
                            {invoices.length === 0 ? <Empty>Sin facturas todavía.</Empty> : <DocumentTable rows={invoices} showType showDueDate />}
                        </Panel>
                    </TabsContent>

                    <TabsContent value="quotes">
                        <Panel title="Presupuestos">{quotes.length === 0 ? <Empty>Sin presupuestos todavía.</Empty> : <DocumentTable rows={quotes} />}</Panel>
                    </TabsContent>

                    <TabsContent value="details" className="grid gap-4 md:grid-cols-2">
                        <BillingPanel customer={customer} />
                        <ContactsPanel customer={customer} />
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
                    </TabsContent>
                </Tabs>

                <aside aria-label="Notas" className="xl:sticky xl:top-6">
                    <NotesPanel key={customer.notes} customer={customer} />
                </aside>
            </div>
        </>
    );
}

/** Notas del cliente: un único bloque de texto que solo ve el usuario. */
function NotesPanel({ customer }: { customer: CustomerDetail }) {
    const [notes, setNotes] = useState(customer.notes);
    const [saving, setSaving] = useState(false);
    const { errors } = usePage().props;
    const changed = notes !== customer.notes;

    function save() {
        router.patch(
            `${CUSTOMERS_URL}/${customer.id}/notes`,
            { notes: notes || null },
            { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) },
        );
    }

    return (
        <Panel title="Notas">
            <label htmlFor="customer-notes" className="mb-2 block text-sm text-muted-foreground">
                Solo para ti: no salen en ningún documento.
            </label>
            <Textarea
                id="customer-notes"
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                rows={10}
                placeholder="Horario, persona de contacto en administración, condiciones acordadas…"
                className="min-h-48 resize-y"
            />
            <FieldError message={errors.notes} />
            <Button variant="outline" className="mt-3 w-full" onClick={save} disabled={saving || !changed}>
                <Save aria-hidden />
                {saving ? 'Guardando…' : changed ? 'Guardar notas' : 'Guardado'}
            </Button>
        </Panel>
    );
}

function BillingPanel({ customer }: { customer: CustomerDetail }) {
    const contactEmail = customer.contacts.find((contact) => contact.is_default)?.email;

    return (
        <Panel title="Datos de facturación">
            <dl className="flex flex-col gap-2 text-sm">
                <Row label={customer.tax_id_type_label ?? 'NIF'}>{customer.tax_id || 'Sin NIF (solo factura simplificada)'}</Row>
                <Row label="Dirección">{customer.address_line ?? '—'}</Row>
                <Row label="Email">{contactEmail || customer.email || '—'}</Row>
                <Row label="Plazo de pago">{customer.payment_terms_days === 0 ? 'Al contado' : `${customer.payment_terms_days} días`}</Row>
                {customer.default_vat_rate ? <Row label="IVA por defecto">{decimalForInput(customer.default_vat_rate)} %</Row> : null}
                {customer.irpf_applies && customer.kind === 'business' ? <Row label="Retención">Se aplica IRPF</Row> : null}
                {customer.surcharge_applies ? <Row label="Recargo">Recargo de equivalencia</Row> : null}
            </dl>
        </Panel>
    );
}

function ContactsPanel({ customer }: { customer: CustomerDetail }) {
    return (
        <Panel title="Contactos">
            {customer.contacts.length === 0 ? (
                <p className="text-sm text-muted-foreground">Sin contactos: los documentos se envían al email de facturación.</p>
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
    );
}

function DocumentTable({ rows, showType, showDueDate }: { rows: DocumentRow[]; showType?: boolean; showDueDate?: boolean }) {
    return (
        <ul className="-mx-4 -mb-4">
            {rows.map((row) => (
                <li key={row.id} className="border-t">
                    <Link href={`/${DOCUMENT_SEGMENTS[row.type]}/${row.id}`} className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50">
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium text-primary">{row.full_number ?? (row.status === 'draft' ? 'Borrador' : 'Sin número')}</span>
                            <span className="block text-xs text-muted-foreground">
                                {[
                                    showType ? DOCUMENT_TYPE_LABELS[row.type] : null,
                                    row.issue_date ? formatDate(row.issue_date) : null,
                                    showDueDate && row.due_date ? `vence el ${formatDate(row.due_date)}` : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </span>
                        <span className="text-right font-semibold tabular-nums">{row.total_formatted}</span>
                        <span className="hidden w-24 text-right sm:block">
                            <RowStatus status={row.status} statusLabel={row.status_label} paymentStatus={row.payment_status} paymentStatusLabel={row.payment_status_label} />
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
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

function Empty({ children }: { children: ReactNode }) {
    return <p className="py-6 text-center text-sm text-muted-foreground">{children}</p>;
}
