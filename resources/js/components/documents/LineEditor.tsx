import { usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';

import { ProductPicker } from '@/components/products/ProductPicker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { decimalForInput } from '@/lib/decimals';
import { formatPercentage } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { ProductRow } from '@/types/catalog';
import type { DraftDefaults, DraftLine } from '@/types/documents';

/** Valor del desplegable de exención cuando no hay ninguna (Radix no admite ''). */
const NO_EXEMPTION = 'none';

const ZERO_RATE = '0.00';


/**
 * Líneas del borrador editadas sobre el propio documento. No calcula importes:
 * la base de cada línea la devuelve el servidor al guardar.
 */
export function LineEditor({
    lines,
    onChange,
    errors,
    defaults,
    showSurcharge,
    showIrpf,
    stale,
}: {
    lines: DraftLine[];
    onChange: (lines: DraftLine[]) => void;
    errors: Record<string, string>;
    defaults: DraftDefaults;
    showSurcharge: boolean;
    showIrpf: boolean;
    stale: boolean;
}) {
    const { invoiceConfig } = usePage().props;

    function surchargeFor(vatRate: string): string {
        return showSurcharge ? (invoiceConfig.surchargeByVatRate[vatRate] ?? ZERO_RATE) : ZERO_RATE;
    }

    /** Las posiciones siempre son 1..n en el orden en que se ven. */
    function commit(next: DraftLine[]) {
        onChange(next.map((line, index) => ({ ...line, position: index + 1 })));
    }

    function update(index: number, changes: Partial<DraftLine>) {
        commit(lines.map((line, i) => (i === index ? { ...line, ...changes } : line)));
    }

    function move(index: number, offset: number) {
        const target = index + offset;

        if (target < 0 || target >= lines.length) {
            return;
        }

        const next = [...lines];
        const [moved] = next.splice(index, 1);

        if (moved) {
            next.splice(target, 0, moved);
        }

        commit(next);
    }

    function add() {
        commit([
            ...lines,
            {
                id: null,
                position: lines.length + 1,
                product_id: null,
                description: '',
                quantity: '1',
                unit: 'ud',
                unit_price: '',
                discount_percent: '0',
                vat_rate: defaults.vat_rate,
                surcharge_rate: surchargeFor(defaults.vat_rate),
                irpf_applies: showIrpf,
                exemption_code: null,
            },
        ]);
    }

    /**
     * Línea a partir de un producto del catálogo: copia descripción, precio, unidad
     * e IVA (el producto queda solo como referencia). Si la última línea está
     * vacía, la rellena en vez de añadir otra.
     */
    function addProduct(product: ProductRow) {
        const vatRate = product.exemption_code ? ZERO_RATE : product.vat_rate;
        const last = lines.at(-1);
        const reuseLast = last !== undefined && !last.id && last.description.trim() === '' && last.unit_price.trim() === '';
        const base = reuseLast ? lines.slice(0, -1) : lines;

        commit([
            ...base,
            {
                id: null,
                position: base.length + 1,
                product_id: product.id,
                description: product.line_description,
                quantity: '1',
                unit: product.unit,
                unit_price: decimalForInput(product.unit_price),
                discount_percent: '0',
                vat_rate: vatRate,
                surcharge_rate: surchargeFor(vatRate),
                irpf_applies: showIrpf && product.irpf_applicable,
                exemption_code: product.exemption_code,
            },
        ]);
    }

    const error = (index: number, field: string) => errors[`lines.${index}.${field}`];

    return (
        <div>
            <div className="mb-2 hidden grid-cols-[1fr_auto] text-xs font-medium tracking-wide text-muted-foreground uppercase sm:grid">
                <span>Descripción</span>
                <span>Total</span>
            </div>

            {lines.length === 0 ? <p className="py-4 text-sm text-muted-foreground">Añade la primera línea del documento.</p> : null}

            <ol className="flex flex-col">
                {lines.map((line, index) => {
                    const vatIsZero = line.vat_rate === ZERO_RATE;

                    return (
                        <li key={line.id ?? `new-${index}`} className="border-t py-4 first:border-t-0">
                            {/* En móvil la descripción ocupa todo el ancho y el importe y los botones bajan. */}
                            <div className="flex flex-wrap items-start gap-x-3 gap-y-1 sm:flex-nowrap">
                                <div className="w-full min-w-0 sm:w-auto sm:flex-1">
                                    <Textarea
                                        aria-label={`Descripción de la línea ${index + 1}`}
                                        value={line.description}
                                        onChange={(event) => update(index, { description: event.target.value })}
                                        placeholder="Concepto: producto o servicio"
                                        rows={1}
                                        className="min-h-9 resize-y font-medium"
                                        aria-invalid={Boolean(error(index, 'description'))}
                                    />
                                    <FieldError message={error(index, 'description')} />
                                </div>
                                <p className={cn('ml-auto shrink-0 pt-2 text-right font-semibold tabular-nums sm:ml-0 sm:w-24', stale && 'opacity-50')}>
                                    {line.line_base_formatted ?? '—'}
                                </p>
                                <div className="flex shrink-0 items-center">
                                    <IconButton label="Subir línea" disabled={index === 0} onClick={() => move(index, -1)}>
                                        <ArrowUp />
                                    </IconButton>
                                    <IconButton label="Bajar línea" disabled={index === lines.length - 1} onClick={() => move(index, 1)}>
                                        <ArrowDown />
                                    </IconButton>
                                    <IconButton label="Quitar línea" onClick={() => commit(lines.filter((_, i) => i !== index))}>
                                        <Trash2 />
                                    </IconButton>
                                </div>
                            </div>

                            <div className="mt-2 flex flex-wrap items-start gap-2">
                                <Field label="Cantidad" error={error(index, 'quantity')} className="w-24">
                                    <Input
                                        inputMode="decimal"
                                        value={line.quantity}
                                        onChange={(event) => update(index, { quantity: event.target.value })}
                                        aria-invalid={Boolean(error(index, 'quantity'))}
                                        className="text-right tabular-nums"
                                    />
                                </Field>
                                <Field label="Unidad" className="w-20">
                                    <Input value={line.unit} onChange={(event) => update(index, { unit: event.target.value })} />
                                </Field>
                                <Field label="Precio (€)" error={error(index, 'unit_price')} className="w-28">
                                    <Input
                                        inputMode="decimal"
                                        value={line.unit_price}
                                        onChange={(event) => update(index, { unit_price: event.target.value })}
                                        placeholder="0,000"
                                        aria-invalid={Boolean(error(index, 'unit_price'))}
                                        className="text-right tabular-nums"
                                    />
                                </Field>
                                <Field label="Dto. %" error={error(index, 'discount_percent')} className="w-20">
                                    <Input
                                        inputMode="decimal"
                                        value={line.discount_percent}
                                        onChange={(event) => update(index, { discount_percent: event.target.value })}
                                        className="text-right tabular-nums"
                                    />
                                </Field>
                                <Field label="IVA" error={error(index, 'vat_rate')} className="w-24">
                                    <Select
                                        value={line.vat_rate}
                                        onValueChange={(value) =>
                                            update(index, {
                                                vat_rate: value,
                                                surcharge_rate: surchargeFor(value),
                                                exemption_code: value === ZERO_RATE ? line.exemption_code : null,
                                            })
                                        }
                                    >
                                        <SelectTrigger className="w-full" aria-label={`IVA de la línea ${index + 1}`}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {invoiceConfig.vatRates.map((rate) => (
                                                <SelectItem key={rate} value={rate}>
                                                    {formatPercentage(rate)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>

                                {vatIsZero ? (
                                    <Field label="Causa de exención" error={error(index, 'exemption_code')} className="min-w-56 flex-1">
                                        <Select
                                            value={line.exemption_code ?? NO_EXEMPTION}
                                            onValueChange={(value) => update(index, { exemption_code: value === NO_EXEMPTION ? null : value })}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={Boolean(error(index, 'exemption_code'))}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NO_EXEMPTION}>Elige la causa…</SelectItem>
                                                {Object.entries(invoiceConfig.exemptionCodes).map(([code, text]) => (
                                                    <SelectItem key={code} value={code}>
                                                        {code} · {text}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                ) : null}

                                {showSurcharge && !vatIsZero ? (
                                    <Field label="Recargo" className="w-24">
                                        <Select value={line.surcharge_rate} onValueChange={(value) => update(index, { surcharge_rate: value })}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {invoiceConfig.surchargeRates.map((rate) => (
                                                    <SelectItem key={rate} value={rate}>
                                                        {formatPercentage(rate)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                ) : null}

                                {showIrpf ? (
                                    <label className="flex h-[3.75rem] items-end gap-2 pb-2 text-sm">
                                        <Checkbox
                                            checked={line.irpf_applies}
                                            onCheckedChange={(checked) => update(index, { irpf_applies: checked === true })}
                                        />
                                        Retención IRPF
                                    </label>
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ol>

            <div className="mt-2 flex flex-wrap items-center gap-1">
                <Button type="button" variant="ghost" className="text-primary" onClick={add}>
                    <Plus aria-hidden />
                    Añadir línea
                </Button>
                <ProductPicker onSelect={addProduct} />
            </div>
            <FieldError message={errors.lines} />
        </div>
    );
}

/**
 * La etiqueta envuelve al campo (queda asociada sin ids); el error va fuera
 * para que no forme parte del nombre accesible del campo.
 */
function Field({ label, error, className, children }: { label: string; error?: string; className?: string; children: ReactNode }) {
    return (
        <div className={className}>
            <label className="block">
                <span className="mb-1 block text-xs text-muted-foreground">{label}</span>
                {children}
            </label>
            <FieldError message={error} />
        </div>
    );
}

export function FieldError({ message }: { message?: string }) {
    return message ? <p className="mt-1 text-xs text-destructive">{message}</p> : null;
}

function IconButton({ label, disabled, onClick, children }: { label: string; disabled?: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <Button type="button" variant="ghost" size="icon" aria-label={label} title={label} disabled={disabled} onClick={onClick} className="size-8 text-muted-foreground">
            {children}
        </Button>
    );
}
