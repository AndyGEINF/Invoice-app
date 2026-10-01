import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, Eye, Save, Trash2 } from 'lucide-react';
import { useEffect } from 'react';

import { DocumentPaper, PaperCustomerAddress } from '@/components/documents/DocumentPaper';
import { FieldError, LineEditor } from '@/components/documents/LineEditor';
import { RowStatus, StatusBadge } from '@/components/documents/StatusBadge';
import { TaxBreakdown } from '@/components/documents/TaxBreakdown';
import { StatusPill } from '@/components/StatusPill';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { decimalForInput, decimalForServer } from '@/lib/decimals';
import { DOCUMENT_LIST_TITLES, documentUrl } from '@/lib/documents';
import type {
    CustomerOption,
    DocumentAbilities,
    DocumentDetail,
    DocumentEventView,
    DocumentRelations,
    DocumentSendView,
    DocumentTypeProps,
    DraftDefaults,
    DraftForm,
    PaperView,
    SeriesOption,
} from '@/types/documents';

interface Props {
    type: DocumentTypeProps;
    document: DocumentDetail | null;
    paper: PaperView;
    form: DraftForm | null;
    customers: CustomerOption[];
    defaults: DraftDefaults;
    events: DocumentEventView[];
    sends: DocumentSendView[];
    related: DocumentRelations | null;
    can: DocumentAbilities;
    series: SeriesOption[];
}

/** Valor del selector de cliente para "sin cliente" (Radix no admite ''). */
const NO_CUSTOMER = 'none';

const ZERO_RATE = '0.00';

/**
 * Vista de un documento tipo papel. Si es borrador (o nuevo) se edita sobre el
 * propio papel; si está emitido es de solo lectura.
 */
export default function DocumentShow(props: Props) {
    const { type, document, form } = props;
    const title = document?.full_number ?? (document ? 'Borrador' : `Nueva ${type.label.toLowerCase()}`);

    return (
        <>
            <Head title={title} />

            <Link href={documentUrl(type)} className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ChevronLeft className="size-4" />
                {DOCUMENT_LIST_TITLES[type.value]}
            </Link>

            {form ? <DraftEditor key={`${form.id ?? 'new'}-${form.version ?? ''}`} {...props} form={form} /> : <ReadOnlyDocument {...props} />}
        </>
    );
}

type DraftData = Omit<DraftForm, 'id' | 'version' | 'breakdown'>;

/** Datos del servidor → campos del formulario (decimales con coma). */
function toData(form: DraftForm): DraftData {
    const { id: _id, version: _version, breakdown: _breakdown, ...data } = form;

    return {
        ...data,
        global_discount_percent: decimalForInput(data.global_discount_percent),
        irpf_rate: decimalForInput(data.irpf_rate),
        lines: data.lines.map((line) => ({
            ...line,
            quantity: decimalForInput(line.quantity),
            unit_price: decimalForInput(line.unit_price),
            discount_percent: decimalForInput(line.discount_percent),
        })),
    };
}

/** Campos del formulario → lo que espera el servidor (decimales con punto). */
function toPayload(data: DraftData): DraftData {
    return {
        ...data,
        global_discount_percent: decimalForServer(data.global_discount_percent),
        irpf_rate: decimalForServer(data.irpf_rate),
        lines: data.lines.map((line) => ({
            ...line,
            quantity: decimalForServer(line.quantity),
            unit_price: decimalForServer(line.unit_price),
            discount_percent: decimalForServer(line.discount_percent),
        })),
    };
}

function DraftEditor({ type, document, paper, form: saved, customers, defaults, can }: Props & { form: DraftForm }) {
    const { invoiceConfig } = usePage().props;
    const form = useForm<DraftData>(toData(saved));
    const errors = form.errors as Record<string, string>;
    const customer = customers.find((option) => option.id === form.data.customer_id) ?? null;
    const isNew = document === null;
    const stale = isNew || form.isDirty;

    function save() {
        const options = { preserveScroll: true };
        form.transform(toPayload);

        if (isNew) {
            form.post(documentUrl(type), options);
        } else {
            form.put(documentUrl(type, document.id), options);
        }
    }

    // Ctrl+S (o Cmd+S) guarda el borrador.
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                save();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    });

    /** Al cambiar de cliente, las líneas heredan si se le aplica recargo o retención. */
    function changeCustomer(value: string) {
        const next = customers.find((option) => option.id === value) ?? null;

        form.setData({
            ...form.data,
            customer_id: next?.id ?? null,
            lines: form.data.lines.map((line) => ({
                ...line,
                irpf_applies: next?.irpf_applies ?? false,
                surcharge_rate: next?.surcharge_applies ? (invoiceConfig.surchargeByVatRate[line.vat_rate] ?? ZERO_RATE) : ZERO_RATE,
            })),
        });
    }

    function destroy() {
        if (document && window.confirm('¿Borrar este borrador? No se puede deshacer.')) {
            router.delete(documentUrl(type, document.id));
        }
    }

    const total = saved.breakdown?.totals_formatted.total ?? '0,00 €';

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="min-w-0">
                {errors.domain ? (
                    <Alert variant="destructive" className="mb-4">
                        <AlertTitle>{errors.domain}</AlertTitle>
                    </Alert>
                ) : null}

                <DocumentPaper
                    paper={paper}
                    title={type.label}
                    status={<StatusPill tone="neutral" label="Borrador" />}
                    dates={
                        <div className="flex flex-col items-end gap-2">
                            <p>
                                <span className="text-muted-foreground">Fecha de expedición</span> al emitir
                            </p>
                            <label className="flex items-center gap-2">
                                <span className="text-muted-foreground">Vencimiento</span>
                                <Input
                                    type="date"
                                    value={form.data.due_date ?? ''}
                                    onChange={(event) => form.setData('due_date', event.target.value || null)}
                                    className="h-8 w-40"
                                    title="Vacío: según el plazo de pago del cliente"
                                />
                            </label>
                            <FieldError message={errors.due_date} />
                        </div>
                    }
                    customer={
                        <div className="max-w-sm">
                            <Select value={form.data.customer_id ?? NO_CUSTOMER} onValueChange={changeCustomer}>
                                <SelectTrigger className="w-full" aria-label="Cliente" aria-invalid={Boolean(errors.customer_id)}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_CUSTOMER}>Sin cliente (factura simplificada)</SelectItem>
                                    {customers.map((option) => (
                                        <SelectItem key={option.id} value={option.id}>
                                            {option.legal_name}
                                            {option.tax_id ? <span className="text-muted-foreground"> · {option.tax_id}</span> : null}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={errors.customer_id} />
                            {form.data.customer_id && form.data.customer_id === saved.customer_id ? (
                                <PaperCustomerAddress paper={paper} />
                            ) : customer?.tax_id ? (
                                <p className="mt-1 text-sm text-muted-foreground">NIF {customer.tax_id}</p>
                            ) : null}
                        </div>
                    }
                    total={total}
                    lines={
                        <LineEditor
                            lines={form.data.lines}
                            onChange={(lines) => form.setData('lines', lines)}
                            errors={errors}
                            defaults={defaults}
                            showSurcharge={customer?.surcharge_applies ?? false}
                            showIrpf={customer?.irpf_applies ?? false}
                            stale={stale}
                        />
                    }
                    notes={
                        <div className="flex flex-col gap-3">
                            <label className="block">
                                <span className="mb-1 block font-medium">Información y detalles de pago</span>
                                <Textarea
                                    value={form.data.notes ?? ''}
                                    onChange={(event) => form.setData('notes', event.target.value || null)}
                                    placeholder="Ej.: pago por transferencia a ES00 0000 0000 0000 0000 0000"
                                    rows={3}
                                />
                            </label>
                            <div className="flex flex-wrap gap-3">
                                <label className="w-36">
                                    <span className="mb-1 block text-xs text-muted-foreground">Descuento global %</span>
                                    <Input
                                        inputMode="decimal"
                                        value={form.data.global_discount_percent}
                                        onChange={(event) => form.setData('global_discount_percent', event.target.value)}
                                        className="text-right tabular-nums"
                                    />
                                    <FieldError message={errors.global_discount_percent} />
                                </label>
                                {customer?.irpf_applies ? (
                                    <label className="w-36">
                                        <span className="mb-1 block text-xs text-muted-foreground">Retención IRPF %</span>
                                        <Input
                                            inputMode="decimal"
                                            value={form.data.irpf_rate}
                                            onChange={(event) => form.setData('irpf_rate', event.target.value)}
                                            className="text-right tabular-nums"
                                        />
                                        <FieldError message={errors.irpf_rate} />
                                    </label>
                                ) : null}
                            </div>
                        </div>
                    }
                    totals={<TaxBreakdown breakdown={saved.breakdown} stale={stale} />}
                />
            </div>

            <aside className="flex flex-col gap-4 lg:sticky lg:top-6">
                <div className="rounded-xl border bg-card p-4">
                    <div className="flex items-center justify-between gap-2">
                        <StatusPill tone="neutral" label="Borrador" />
                        <span className="text-xs text-muted-foreground">{isNew ? 'Sin guardar' : form.isDirty ? 'Cambios sin guardar' : 'Guardado'}</span>
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">Total</p>
                    <p className="text-2xl font-semibold tabular-nums">{total}</p>

                    <Button className="mt-4 w-full" onClick={save} disabled={form.processing || (!isNew && !form.isDirty)}>
                        <Save aria-hidden />
                        {form.processing ? 'Guardando…' : 'Guardar borrador'}
                    </Button>
                    <p className="mt-2 text-center text-xs text-muted-foreground">También con Ctrl + S</p>

                    {document ? (
                        <div className="mt-4 flex flex-col gap-1 border-t pt-4">
                            <Button variant="ghost" className="justify-start" asChild>
                                <a href={documentUrl(type, document.id, 'preview')} target="_blank" rel="noreferrer">
                                    <Eye aria-hidden />
                                    Vista previa
                                </a>
                            </Button>
                            {can.delete ? (
                                <Button variant="ghost" className="justify-start text-destructive hover:text-destructive" onClick={destroy}>
                                    <Trash2 aria-hidden />
                                    Borrar borrador
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </aside>
        </div>
    );
}

function ReadOnlyDocument({ type, document, paper }: Props) {
    if (!document) {
        return null;
    }

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <DocumentPaper paper={paper} className="min-w-0" />

            <aside className="flex flex-col gap-4 lg:sticky lg:top-6">
                <div className="rounded-xl border bg-card p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={document.status} label={document.status_label} />
                        <RowStatus
                            status={document.status}
                            statusLabel={document.status_label}
                            paymentStatus={document.payment_status}
                            paymentStatusLabel={document.payment_status_label}
                        />
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">Total</p>
                    <p className="text-2xl font-semibold tabular-nums">{paper.total}</p>

                    <Button variant="outline" className="mt-4 w-full" asChild>
                        <a href={documentUrl(type, document.id, 'preview')} target="_blank" rel="noreferrer">
                            <Eye aria-hidden />
                            Vista previa
                        </a>
                    </Button>
                </div>
            </aside>
        </div>
    );
}
