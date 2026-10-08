import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Save } from 'lucide-react';

import { Field, FormSection } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { decimalForInput, decimalForServer } from '@/lib/decimals';
import { cn } from '@/lib/utils';
import type { Option, ProductRow } from '@/types/catalog';

interface Props {
    product: ProductRow | null;
    options: { types: Option[]; default_unit: string; default_vat_rate: string };
}

const PRODUCTS_URL = '/products';

/** Valor del desplegable de IVA que marca la línea como exenta. */
const EXEMPT = 'exempt';

/**
 * Alta y edición de un producto o servicio. Cambiarlo no altera ninguna factura:
 * las líneas guardan su propia copia de descripción, precio e IVA.
 */
export default function ProductFormPage({ product, options }: Props) {
    const { invoiceConfig } = usePage().props;
    const productUrl = product ? `${PRODUCTS_URL}/${product.id}` : null;

    const form = useForm({
        type: product?.type ?? 'service',
        name: product?.name ?? '',
        sku: product?.sku ?? '',
        description: product?.description ?? '',
        // El usuario escribe con coma; se convierte a punto solo al enviar.
        unit_price: product ? decimalForInput(product.unit_price) : '',
        unit: product?.unit ?? options.default_unit,
        vat_rate: product?.vat_rate ?? options.default_vat_rate,
        exemption_code: product?.exemption_code ?? null,
        irpf_applicable: product?.irpf_applicable ?? false,
    });
    const errors = form.errors as Record<string, string>;
    const isExempt = form.data.exemption_code !== null;

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, unit_price: decimalForServer(data.unit_price) }));

        if (productUrl) {
            form.put(productUrl, { preserveScroll: true });
        } else {
            form.post(PRODUCTS_URL, { preserveScroll: true });
        }
    }

    function setVat(value: string) {
        if (value === EXEMPT) {
            form.setData((data) => ({ ...data, exemption_code: Object.keys(invoiceConfig.exemptionCodes)[0] ?? null }));

            return;
        }

        form.setData((data) => ({ ...data, vat_rate: value, exemption_code: null }));
    }

    const title = product ? product.name : 'Nuevo producto o servicio';

    return (
        <>
            <Head title={title} />

            <form onSubmit={submit} className="mx-auto max-w-3xl">
                <PageHeader
                    title={title}
                    description={product ? 'Los cambios no afectan a las facturas ya creadas.' : 'Lo que vendes, con el precio y el IVA que propone en cada factura.'}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={PRODUCTS_URL}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Save aria-hidden />
                                {form.processing ? 'Guardando…' : 'Guardar'}
                            </Button>
                        </>
                    }
                />

                <FormSection title="Qué es">
                    <fieldset className="mb-5">
                        <legend className="mb-1.5 text-sm font-medium">Tipo</legend>
                        <div className="inline-flex rounded-lg border bg-card p-0.5 text-sm">
                            {options.types.map((type) => (
                                <button
                                    key={type.value}
                                    type="button"
                                    aria-pressed={form.data.type === type.value}
                                    onClick={() => form.setData('type', type.value as ProductRow['type'])}
                                    className={cn(
                                        'rounded-md px-4 py-1.5 transition-colors',
                                        form.data.type === type.value ? 'bg-primary font-medium text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {type.label}
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <div className="grid gap-4 md:grid-cols-[1fr_12rem]">
                        <Field id="name" label="Nombre" required error={errors.name}>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        </Field>
                        <Field id="sku" label="Referencia" hint="Opcional y única." error={errors.sku}>
                            <Input id="sku" value={form.data.sku} onChange={(event) => form.setData('sku', event.target.value.toUpperCase())} />
                        </Field>
                        <Field id="description" label="Descripción" hint="Se copia a la línea de la factura, debajo del nombre." error={errors.description} className="md:col-span-2">
                            <Textarea id="description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows={3} />
                        </Field>
                    </div>
                </FormSection>

                <FormSection title="Precio e impuestos" description="Precio sin IVA. Admite hasta tres decimales (33,333 €).">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field id="unit_price" label="Precio unitario (€)" required error={errors.unit_price}>
                            <Input
                                id="unit_price"
                                inputMode="decimal"
                                value={form.data.unit_price}
                                onChange={(event) => form.setData('unit_price', event.target.value)}
                                placeholder="0,00"
                            />
                        </Field>
                        <Field id="unit" label="Unidad" hint="ud, h, kg, m²…" error={errors.unit}>
                            <Input id="unit" value={form.data.unit} onChange={(event) => form.setData('unit', event.target.value)} />
                        </Field>
                        <Field id="vat_rate" label="IVA" required error={errors.vat_rate}>
                            <Select value={isExempt ? EXEMPT : form.data.vat_rate} onValueChange={setVat}>
                                <SelectTrigger id="vat_rate" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {invoiceConfig.vatRates.map((rate) => (
                                        <SelectItem key={rate} value={rate}>
                                            {decimalForInput(rate)} %
                                        </SelectItem>
                                    ))}
                                    <SelectItem value={EXEMPT}>Exento o no sujeto</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>

                        {isExempt ? (
                            <Field id="exemption_code" label="Causa de exención" required error={errors.exemption_code} className="md:col-span-3">
                                <Select value={form.data.exemption_code ?? undefined} onValueChange={(value) => form.setData('exemption_code', value)}>
                                    <SelectTrigger id="exemption_code" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(invoiceConfig.exemptionCodes).map(([code, label]) => (
                                            <SelectItem key={code} value={code}>
                                                {code} · {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        ) : null}

                        <div className="flex items-start gap-3 rounded-lg border p-3 md:col-span-3">
                            <Checkbox
                                id="irpf_applicable"
                                checked={form.data.irpf_applicable}
                                onCheckedChange={(value) => form.setData('irpf_applicable', value === true)}
                                className="mt-0.5"
                            />
                            <div>
                                <Label htmlFor="irpf_applicable">Lleva retención de IRPF</Label>
                                <p className="text-xs text-muted-foreground">
                                    Para servicios profesionales. Solo se aplica cuando el cliente es una empresa o un profesional.
                                </p>
                            </div>
                        </div>
                    </div>
                </FormSection>

                <div className="flex justify-end pb-6">
                    <Button type="submit" disabled={form.processing}>
                        <Save aria-hidden />
                        {form.processing ? 'Guardando…' : 'Guardar'}
                    </Button>
                </div>
            </form>
        </>
    );
}
