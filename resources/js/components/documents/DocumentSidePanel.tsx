import { Link, router, usePage } from '@inertiajs/react';
import { Eye, FileText, Save, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { IssueDialog } from '@/components/documents/IssueDialog';
import { FieldError } from '@/components/documents/LineEditor';
import { PaymentStatusBadge, StatusBadge } from '@/components/documents/StatusBadge';
import { StatusPill } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate, formatDateTime } from '@/lib/dates';
import { documentUrl } from '@/lib/documents';
import { ISSUER_MISSING_LABELS } from '@/lib/issuer';
import type {
    DocumentAbilities,
    DocumentDetail,
    DocumentEventView,
    DocumentRelations,
    DocumentSendView,
    DocumentTypeProps,
    RelatedLink,
    SeriesOption,
} from '@/types/documents';

/** Estado del borrador que se edita en el papel (solo en borradores). */
export interface DraftState {
    isNew: boolean;
    isDirty: boolean;
    processing: boolean;
    onSave: () => void;
    /** Sin NIF del cliente: se emitiría como factura simplificada. */
    simplified: boolean;
}

/** La pestaña activa va en azul: es lo único con color en el panel. */
const TAB_TRIGGER_CLASS = 'data-active:bg-primary data-active:text-primary-foreground dark:data-active:bg-primary dark:data-active:text-primary-foreground';

/**
 * Panel derecho de la vista de documento: General, Envíos, Historial y Notas
 * internas. Las acciones salen de `can`, que calcula el servidor.
 */
export function DocumentSidePanel({
    type,
    document,
    total,
    can,
    events,
    sends,
    related,
    series,
    draft,
}: {
    type: DocumentTypeProps;
    document: DocumentDetail | null;
    total: string;
    can: DocumentAbilities;
    events: DocumentEventView[];
    sends: DocumentSendView[];
    related: DocumentRelations | null;
    series: SeriesOption[];
    draft?: DraftState;
}) {
    return (
        <aside aria-label="Panel del documento" className="flex flex-col gap-4 lg:sticky lg:top-6">
            <Tabs defaultValue="general" className="rounded-xl border bg-card p-3">
                <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="general" className={TAB_TRIGGER_CLASS}>
                        General
                    </TabsTrigger>
                    <TabsTrigger value="sends" className={TAB_TRIGGER_CLASS}>
                        Envíos
                    </TabsTrigger>
                    <TabsTrigger value="history" className={TAB_TRIGGER_CLASS}>
                        Historial
                    </TabsTrigger>
                    <TabsTrigger value="notes" className={TAB_TRIGGER_CLASS}>
                        Notas
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="general" className="px-1 pt-3">
                    <GeneralTab type={type} document={document} total={total} can={can} related={related} series={series} draft={draft} />
                </TabsContent>
                <TabsContent value="sends" className="px-1 pt-3">
                    <SendsTab document={document} sends={sends} />
                </TabsContent>
                <TabsContent value="history" className="px-1 pt-3">
                    <HistoryTab events={events} />
                </TabsContent>
                <TabsContent value="notes" className="px-1 pt-3">
                    <NotesTab type={type} document={document} />
                </TabsContent>
            </Tabs>
        </aside>
    );
}

function GeneralTab({
    type,
    document,
    total,
    can,
    related,
    series,
    draft,
}: {
    type: DocumentTypeProps;
    document: DocumentDetail | null;
    total: string;
    can: DocumentAbilities;
    related: DocumentRelations | null;
    series: SeriesOption[];
    draft?: DraftState;
}) {
    const [issuing, setIssuing] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1.5">
                    {document ? <StatusBadge status={document.status} label={document.status_label} /> : <StatusPill tone="neutral" label="Borrador" />}
                    {document?.payment_status && document.payment_status_label ? (
                        <PaymentStatusBadge status={document.payment_status} label={document.payment_status_label} />
                    ) : null}
                </div>
                {draft ? (
                    <span className="text-xs text-muted-foreground">{draft.isNew ? 'Sin guardar' : draft.isDirty ? 'Cambios sin guardar' : 'Guardado'}</span>
                ) : null}
            </div>

            <div>
                <p className="text-sm text-muted-foreground">Total</p>
                <p className="text-2xl font-semibold tabular-nums">{total}</p>
            </div>

            {document ? (
                <dl className="flex flex-col gap-2 text-sm">
                    <DetailRow label="Número" value={document.full_number ?? 'Al emitir'} />
                    <DetailRow label="Cliente" value={document.customer?.legal_name ?? 'Sin cliente'} />
                    <DetailRow label="Fecha" value={formatDate(document.issue_date) || 'Al emitir'} />
                    {document.due_date ? <DetailRow label="Vencimiento" value={formatDate(document.due_date)} /> : null}
                    {document.invoice_type_label ? <DetailRow label="Tipo" value={document.invoice_type_label} /> : null}
                    {document.series_code ? <DetailRow label="Serie" value={document.series_code} /> : null}
                </dl>
            ) : null}

            <RelatedLinks related={related} />

            <div className="flex flex-col gap-2 border-t pt-4">
                {draft ? (
                    <>
                        <Button onClick={draft.onSave} disabled={draft.processing || (!draft.isNew && !draft.isDirty)} variant={draft.isNew || draft.isDirty ? 'default' : 'outline'}>
                            <Save aria-hidden />
                            {draft.processing ? 'Guardando…' : 'Guardar borrador'}
                        </Button>
                        <p className="text-center text-xs text-muted-foreground">También con Ctrl + S</p>
                    </>
                ) : null}

                {can.issue && document ? (
                    <>
                        <IssueButton dirty={draft?.isDirty ?? false} onClick={() => setIssuing(true)} label={`Emitir ${type.label.toLowerCase()}`} />
                        <IssueDialog
                            open={issuing}
                            onOpenChange={setIssuing}
                            type={type}
                            documentId={document.id}
                            series={series}
                            simplified={draft?.simplified ?? false}
                        />
                    </>
                ) : null}

                {document ? (
                    <Button variant="ghost" className="justify-start" asChild>
                        <a href={documentUrl(type, document.id, 'preview')} target="_blank" rel="noreferrer">
                            <Eye aria-hidden />
                            Vista previa
                        </a>
                    </Button>
                ) : null}

                {can.delete && document ? (
                    <Button
                        variant="ghost"
                        className="justify-start text-destructive hover:text-destructive"
                        onClick={() => {
                            if (window.confirm('¿Borrar este borrador? No se puede deshacer.')) {
                                router.delete(documentUrl(type, document.id));
                            }
                        }}
                    >
                        <Trash2 aria-hidden />
                        Borrar borrador
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

/**
 * Emitir solo con el emisor completo y sin cambios pendientes; si no, el botón
 * explica qué falta.
 */
function IssueButton({ dirty, onClick, label }: { dirty: boolean; onClick: () => void; label: string }) {
    const { issuer } = usePage().props;
    const reason = !issuer.isComplete
        ? `Completa los datos de tu empresa: falta ${issuer.missing.map((field) => ISSUER_MISSING_LABELS[field]).join(', ')}.`
        : dirty
          ? 'Guarda los cambios antes de emitir.'
          : null;

    if (!reason) {
        return (
            <Button onClick={onClick}>
                <Send aria-hidden />
                {label}
            </Button>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {/* El span recibe el ratón: un botón deshabilitado no lanza eventos. */}
                <span tabIndex={0} className="block">
                    <Button disabled className="w-full">
                        <Send aria-hidden />
                        {label}
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent>{reason}</TooltipContent>
        </Tooltip>
    );
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

function RelatedLinks({ related }: { related: DocumentRelations | null }) {
    if (!related) {
        return null;
    }

    const links: { label: string; link: RelatedLink }[] = [];

    if (related.converted_from) links.push({ label: 'Presupuesto de origen', link: related.converted_from });
    if (related.converted_to) links.push({ label: 'Factura generada', link: related.converted_to });
    if (related.rectifies) links.push({ label: 'Rectifica a', link: related.rectifies });
    related.rectified_by.forEach((link) => links.push({ label: 'Rectificada por', link }));

    if (links.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1 border-t pt-4 text-sm">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Relacionados</p>
            {links.map(({ label, link }) => (
                <Link key={`${label}-${link.id}`} href={`/${link.segment}/${link.id}`} className="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 hover:bg-muted">
                    <span className="text-muted-foreground">{label}</span>
                    <span className="flex items-center gap-1 font-medium text-primary">
                        <FileText className="size-3.5" />
                        {link.full_number ?? 'Borrador'}
                    </span>
                </Link>
            ))}
        </div>
    );
}

function SendsTab({ document, sends }: { document: DocumentDetail | null; sends: DocumentSendView[] }) {
    if (!document || document.status === 'draft') {
        return <EmptyTab>Emite el documento para poder enviarlo al cliente.</EmptyTab>;
    }

    if (sends.length === 0) {
        return <EmptyTab>Todavía no se ha enviado por email.</EmptyTab>;
    }

    return (
        <ul className="flex flex-col gap-2 text-sm">
            {sends.map((send) => (
                <li key={send.id} className="rounded-lg border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <StatusPill tone={send.status === 'sent' ? 'success' : send.status === 'failed' ? 'danger' : 'neutral'} label={send.status_label} />
                        <span className="text-xs text-muted-foreground">{formatDateTime(send.sent_at ?? send.queued_at)}</span>
                    </div>
                    <p className="mt-2 truncate font-medium">{send.subject}</p>
                    <p className="truncate text-xs text-muted-foreground">Para {send.to.join(', ')}</p>
                    {send.error ? <p className="mt-1 text-xs text-destructive">{send.error}</p> : null}
                </li>
            ))}
        </ul>
    );
}

function HistoryTab({ events }: { events: DocumentEventView[] }) {
    if (events.length === 0) {
        return <EmptyTab>El historial empieza al guardar el documento.</EmptyTab>;
    }

    return (
        <ol className="relative flex flex-col gap-4 border-l pl-4 text-sm">
            {[...events].reverse().map((event) => (
                <li key={event.id} className="relative">
                    <span aria-hidden className="absolute top-1.5 -left-[1.3rem] size-2 rounded-full bg-primary" />
                    <p className="font-medium">{event.label}</p>
                    {typeof event.payload?.full_number === 'string' ? <p className="text-muted-foreground">{event.payload.full_number}</p> : null}
                    <p className="text-xs text-muted-foreground">{formatDateTime(event.created_at)}</p>
                </li>
            ))}
        </ol>
    );
}

/** Notas que el cliente nunca ve: no se imprimen y se guardan aparte del borrador. */
function NotesTab({ type, document }: { type: DocumentTypeProps; document: DocumentDetail | null }) {
    const [notes, setNotes] = useState(document?.internal_notes ?? '');
    const [saving, setSaving] = useState(false);
    const { errors } = usePage().props;

    if (!document) {
        return <EmptyTab>Guarda el borrador para poder añadir notas internas.</EmptyTab>;
    }

    const changed = notes !== (document.internal_notes ?? '');

    function save() {
        if (!document) {
            return;
        }

        router.patch(
            `${documentUrl(type, document.id)}/internal-notes`,
            { internal_notes: notes || null },
            { preserveScroll: true, preserveState: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) },
        );
    }

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor="internal-notes" className="text-sm text-muted-foreground">
                Solo para ti: no aparecen en el documento ni las ve el cliente.
            </label>
            <Textarea id="internal-notes" value={notes} onChange={(event) => setNotes(event.target.value)} rows={6} placeholder="Ej.: llamar el lunes para confirmar el pedido" />
            <FieldError message={errors.internal_notes} />
            <Button variant="outline" onClick={save} disabled={saving || !changed}>
                <Save aria-hidden />
                {saving ? 'Guardando…' : 'Guardar notas'}
            </Button>
        </div>
    );
}

function EmptyTab({ children }: { children: ReactNode }) {
    return <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">{children}</p>;
}
