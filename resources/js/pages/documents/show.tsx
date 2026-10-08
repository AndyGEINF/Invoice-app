import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useEffect, useState } from 'react';

import { CustomerPicker } from '@/components/customers/CustomerPicker';
import { DocumentPaper, PaperCustomerAddress } from '@/components/documents/DocumentPaper';
import { DocumentSidePanel } from '@/components/documents/DocumentSidePanel';
import { FieldError, LineEditor } from '@/components/documents/LineEditor';
import { TaxBreakdown } from '@/components/documents/TaxBreakdown';
import { StatusPill } from '@/components/StatusPill';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
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
    /** Cliente actual del borrador; los demás se buscan con CustomerPicker. */
    customer: CustomerOption | null;
    defaults: DraftDefaults;
    events: DocumentEventView[];
    sends: DocumentSendView[];
    related: DocumentRelations | null;
    can: DocumentAbilities;
    series: SeriesOption[];
}

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

            <div className="mx-auto max-w-[112rem]">
                <Link href={documentUrl(type)} className="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ChevronLeft className="size-4" />
                    {DOCUMENT_LIST_TITLES[type.value]}
                </Link>
            </div>

            {/* Clave por documento: guardar notas u otras acciones no reinician el editor ni pierden cambios sin guardar. */}
            {form ? <DraftEditor key={form.id ?? 'new'} {...props} form={form} /> : <ReadOnlyDocument {...props} />}
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

function DraftEditor({ type, document, paper, form: saved, customer: savedCustomer, defaults, can, events, sends, related, series }: Props & { form: DraftForm }) {
    const { invoiceConfig } = usePage().props;
    const form = useForm<DraftData>(toData(saved));
    const errors = form.errors as Record<string, string>;
    // El cliente elegido en el buscador (con lo que cambia el formulario); el id va en form.customer_id.
    const [customer, setCustomer] = useState<CustomerOption | null>(savedCustomer);
    const isNew = document === null;
    const stale = isNew || form.isDirty;

    function save() {
        const options = {
            preserveScroll: true,
            // Tras guardar, el formulario pasa a ser lo que devolvió el servidor
            // (bases por línea, ids de líneas nuevas…) y deja de estar "sucio".
            onSuccess: (page: { props: unknown }) => {
                const next = (page.props as Props).form;

                if (next) {
                    const data = toData(next);
                    form.setDefaults(data);
                    form.setData(data);
                }
            },
        };
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
    function changeCustomer(next: CustomerOption | null) {
        setCustomer(next);
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

    const total = saved.breakdown?.totals_formatted.total ?? '0,00 €';

    return (
        <div className="mx-auto grid max-w-[112rem] items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] 2xl:grid-cols-[minmax(0,1fr)_26rem]">
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
                            <CustomerPicker value={customer} onChange={changeCustomer} invalid={Boolean(errors.customer_id)} />
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

            <DocumentSidePanel
                type={type}
                document={document}
                total={total}
                can={can}
                events={events}
                sends={sends}
                related={related}
                series={series}
                draft={{ isNew, isDirty: form.isDirty, processing: form.processing, onSave: save, simplified: !customer?.tax_id }}
            />
        </div>
    );
}

function ReadOnlyDocument({ type, document, paper, can, events, sends, related, series }: Props) {
    return (
        <div className="mx-auto grid max-w-[112rem] items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] 2xl:grid-cols-[minmax(0,1fr)_26rem]">
            <DocumentPaper paper={paper} className="min-w-0" />

            <DocumentSidePanel type={type} document={document} total={paper.total} can={can} events={events} sends={sends} related={related} series={series} />
        </div>
    );
}
