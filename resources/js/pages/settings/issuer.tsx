import { Head, useForm } from '@inertiajs/react';
import { ImageUp, Save, TriangleAlert } from 'lucide-react';
import { useEffect, useMemo } from 'react';

import { FieldError } from '@/components/documents/LineEditor';
import { Field, FormSection } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ISSUER_MISSING_LABELS } from '@/lib/issuer';
import { cn } from '@/lib/utils';
import type { IssuerMissingField } from '@/types';

interface IssuerAddress {
    street: string;
    city: string;
    postal_code: string;
    province: string;
    country: string;
}

interface Props {
    settings: {
        name: string | null;
        company_name: string | null;
        logo_url: string | null;
        brand_color: string;
        tax_id: string | null;
        address: IssuerAddress;
        vat_regime: string;
        default_irpf_rate: string;
        email: string | null;
        phone: string | null;
        website: string | null;
        invoice_footer: string | null;
        missing: IssuerMissingField[];
    };
    options: { vat_regimes: { value: string; label: string }[]; irpf_rates: string[]; logo_max_kb: number; brand_colors: string[] };
}

const KB_PER_MB = 1024;

/**
 * Datos del emisor: lo que aparece en todas las facturas. Arriba lo que se ve
 * en la cabecera (nombre, empresa y logotipo); debajo los datos fiscales.
 */
export default function IssuerSettings({ settings: issuer, options }: Props) {
    const form = useForm({
        name: issuer.name ?? '',
        company_name: issuer.company_name ?? '',
        logo: null as File | null,
        brand_color: issuer.brand_color,
        tax_id: issuer.tax_id ?? '',
        address: issuer.address,
        vat_regime: issuer.vat_regime,
        default_irpf_rate: issuer.default_irpf_rate.replace(/\.?0+$/, '').replace('.', ','),
        email: issuer.email ?? '',
        phone: issuer.phone ?? '',
        website: issuer.website ?? '',
        invoice_footer: issuer.invoice_footer ?? '',
    });
    const errors = form.errors as Record<string, string>;
    // Vista previa del logotipo elegido antes de guardarlo; si no, el guardado.
    const chosenLogoUrl = useMemo(() => (form.data.logo ? URL.createObjectURL(form.data.logo) : null), [form.data.logo]);
    const preview = chosenLogoUrl ?? issuer.logo_url;

    useEffect(
        () => () => {
            if (chosenLogoUrl) {
                URL.revokeObjectURL(chosenLogoUrl);
            }
        },
        [chosenLogoUrl],
    );

    function submit(event: React.FormEvent) {
        event.preventDefault();
        // El logotipo obliga a enviar multipart: POST con _method=PUT.
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post('/settings/issuer', { forceFormData: true, preserveScroll: true, onSuccess: () => form.setData('logo', null) });
    }

    const legalName = form.data.company_name.trim() || form.data.name.trim();

    return (
        <>
            <Head title="Datos de tu empresa" />

            <form onSubmit={submit} className="mx-auto max-w-4xl">
                <PageHeader
                    title="Datos de tu empresa"
                    description="Aparecen en todas tus facturas y presupuestos."
                    actions={
                        <Button type="submit" disabled={form.processing}>
                            <Save aria-hidden />
                            {form.processing ? 'Guardando…' : 'Guardar'}
                        </Button>
                    }
                />

                {issuer.missing.length > 0 ? (
                    <Alert className="mb-6 border-warning/30 bg-warning-soft text-warning-strong">
                        <TriangleAlert aria-hidden />
                        <AlertTitle>Para poder emitir falta {issuer.missing.map((field) => ISSUER_MISSING_LABELS[field]).join(', ')}.</AlertTitle>
                        <AlertDescription className="text-current">Completa estos datos y guarda.</AlertDescription>
                    </Alert>
                ) : null}

                <FormSection title="Cómo te verán tus clientes" description="Cabecera de cada factura.">
                    <div className="grid gap-6 md:grid-cols-[1fr_16rem]">
                        <div className="flex flex-col gap-4">
                            <Field id="name" label="Tu nombre" required error={errors.name}>
                                <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} autoComplete="name" />
                            </Field>
                            <Field
                                id="company_name"
                                label="Empresa o nombre comercial"
                                hint="Si lo rellenas, facturas a nombre de la empresa; si no, a tu nombre."
                                error={errors.company_name}
                            >
                                <Input
                                    id="company_name"
                                    value={form.data.company_name}
                                    onChange={(event) => form.setData('company_name', event.target.value)}
                                    autoComplete="organization"
                                />
                            </Field>
                            {legalName ? (
                                <p className="text-sm text-muted-foreground">
                                    Tus facturas saldrán a nombre de <span className="font-medium text-foreground">{legalName}</span>.
                                </p>
                            ) : null}
                            <BrandColorField
                                value={form.data.brand_color}
                                swatches={options.brand_colors}
                                error={errors.brand_color}
                                onChange={(color) => form.setData('brand_color', color)}
                            />
                        </div>

                        <Field id="logo" label="Logotipo" required error={errors.logo}>
                            <label
                                htmlFor="logo"
                                className="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed bg-muted/40 p-4 text-center text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                            >
                                {preview ? (
                                    <img src={preview} alt="Vista previa del logotipo" className="max-h-full max-w-full object-contain" />
                                ) : (
                                    <>
                                        <ImageUp className="size-6" aria-hidden />
                                        Subir logotipo
                                    </>
                                )}
                            </label>
                            <input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/svg+xml"
                                className="sr-only"
                                onChange={(event) => form.setData('logo', event.target.files?.[0] ?? null)}
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                PNG, JPG o SVG, hasta {options.logo_max_kb / KB_PER_MB} MB. {preview ? 'Pulsa la imagen para cambiarla.' : ''}
                            </p>
                        </Field>
                    </div>
                </FormSection>

                <FormSection title="Datos fiscales" description="Obligatorios en una factura.">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field id="tax_id" label="NIF" required error={errors.tax_id}>
                            <Input id="tax_id" value={form.data.tax_id} onChange={(event) => form.setData('tax_id', event.target.value.toUpperCase())} />
                        </Field>
                        <Field id="vat_regime" label="Régimen de IVA" required error={errors.vat_regime}>
                            <Select value={form.data.vat_regime} onValueChange={(value) => form.setData('vat_regime', value)}>
                                <SelectTrigger id="vat_regime" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.vat_regimes.map((regime) => (
                                        <SelectItem key={regime.value} value={regime.value}>
                                            {regime.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field id="street" label="Dirección" required error={errors['address.street']} className="md:col-span-2">
                            <Input
                                id="street"
                                value={form.data.address.street}
                                onChange={(event) => form.setData('address', { ...form.data.address, street: event.target.value })}
                                autoComplete="street-address"
                            />
                        </Field>
                        <Field id="postal_code" label="Código postal" required error={errors['address.postal_code']}>
                            <Input
                                id="postal_code"
                                value={form.data.address.postal_code}
                                onChange={(event) => form.setData('address', { ...form.data.address, postal_code: event.target.value })}
                                autoComplete="postal-code"
                            />
                        </Field>
                        <Field id="city" label="Población" required error={errors['address.city']}>
                            <Input id="city" value={form.data.address.city} onChange={(event) => form.setData('address', { ...form.data.address, city: event.target.value })} />
                        </Field>
                        <Field id="province" label="Provincia" error={errors['address.province']}>
                            <Input
                                id="province"
                                value={form.data.address.province}
                                onChange={(event) => form.setData('address', { ...form.data.address, province: event.target.value })}
                            />
                        </Field>
                        <Field
                            id="default_irpf_rate"
                            label="Retención IRPF por defecto (%)"
                            hint="La que propone una factura a empresas. 15 % general, 7 % los primeros años."
                            error={errors.default_irpf_rate}
                        >
                            <Input
                                id="default_irpf_rate"
                                inputMode="decimal"
                                value={form.data.default_irpf_rate}
                                onChange={(event) => form.setData('default_irpf_rate', event.target.value)}
                            />
                        </Field>
                    </div>
                </FormSection>

                <FormSection title="Contacto y pie de factura" description="Opcional.">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Field id="email" label="Email" error={errors.email}>
                            <Input id="email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} autoComplete="email" />
                        </Field>
                        <Field id="phone" label="Teléfono" error={errors.phone}>
                            <Input id="phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} autoComplete="tel" />
                        </Field>
                        <Field id="website" label="Web" error={errors.website}>
                            <Input id="website" type="url" value={form.data.website} onChange={(event) => form.setData('website', event.target.value)} placeholder="https://" />
                        </Field>
                        <Field id="invoice_footer" label="Pie de factura" hint="Ej.: datos registrales o la cuenta para transferencias." error={errors.invoice_footer} className="md:col-span-3">
                            <Textarea id="invoice_footer" value={form.data.invoice_footer} onChange={(event) => form.setData('invoice_footer', event.target.value)} rows={3} />
                        </Field>
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

/** Muestras de color más un selector libre. El color tiñe la cabecera, los títulos y el total del PDF. */
function BrandColorField({ value, swatches, error, onChange }: { value: string; swatches: string[]; error?: string; onChange: (color: string) => void }) {
    const isCustom = !swatches.includes(value.toLowerCase());

    return (
        <fieldset>
            <legend className="mb-1.5 text-sm font-medium">Color de marca</legend>
            <div className="flex flex-wrap items-center gap-2">
                {swatches.map((swatch) => {
                    const selected = swatch === value.toLowerCase();

                    return (
                        <button
                            key={swatch}
                            type="button"
                            onClick={() => onChange(swatch)}
                            aria-label={`Color ${swatch}`}
                            aria-pressed={selected}
                            className={cn(
                                'size-8 rounded-full border-2 border-card ring-offset-2 ring-offset-card transition-shadow focus-visible:outline-none',
                                selected ? 'ring-2 ring-foreground' : 'hover:ring-2 hover:ring-border focus-visible:ring-2 focus-visible:ring-ring',
                            )}
                            style={{ backgroundColor: swatch }}
                        />
                    );
                })}
                <label
                    className={cn(
                        'relative flex h-8 cursor-pointer items-center gap-2 rounded-full border px-3 text-sm text-muted-foreground transition-colors hover:text-foreground',
                        isCustom && 'border-foreground text-foreground',
                    )}
                >
                    <span className="size-4 rounded-full border" style={{ backgroundColor: value }} aria-hidden />
                    {isCustom ? value.toLowerCase() : 'Otro'}
                    <input type="color" value={value} onChange={(event) => onChange(event.target.value)} className="sr-only" aria-label="Elegir otro color" />
                </label>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">Se usa en la cabecera, los títulos y el total de tus facturas.</p>
            <FieldError message={error} />
        </fieldset>
    );
}
