import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Building2, Plus, Save, Trash2, TriangleAlert, User } from 'lucide-react';
import type { ComponentType } from 'react';

import { Field, FormSection } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { decimalForInput } from '@/lib/decimals';
import { cn } from '@/lib/utils';
import type { Address, ContactForm, CustomerForm, CustomerKind, Option } from '@/types/catalog';

interface Props {
    customer: CustomerForm | null;
    options: { kinds: Option[]; tax_id_types: Option[]; default_payment_terms_days: number; max_contacts: number };
}

const CUSTOMERS_URL = '/customers';

/** Valor de los desplegables para "sin elegir" (Radix no admite valores vacíos). */
const NONE = 'none';

const EMPTY_ADDRESS: Address = { street: '', city: '', postal_code: '', province: '', country: 'ES' };

const EMPTY_CONTACT: ContactForm = { name: '', email: '', phone: '', is_default: false };

const KIND_ICONS: Record<CustomerKind, ComponentType<{ className?: string }>> = {
    business: Building2,
    individual: User,
};

const KIND_HINTS: Record<CustomerKind, string> = {
    business: 'Siempre con NIF y dirección. Se le puede aplicar retención de IRPF.',
    individual: 'NIF opcional: sin él solo se le puede hacer factura simplificada. Nunca lleva retención.',
};

/** Alta y edición de un cliente. Si el NIF ya es de otro, avisa y deja continuar. */
export default function CustomerFormPage({ customer, options }: Props) {
    const page = usePage();
    const { invoiceConfig } = page.props;
    const duplicate = page.flash.duplicate_warning;
    const isNew = customer === null;

    const form = useForm({
        kind: customer?.kind ?? ('business' as CustomerKind),
        legal_name: customer?.legal_name ?? '',
        trade_name: customer?.trade_name ?? '',
        tax_id: customer?.tax_id ?? '',
        tax_id_type: customer?.tax_id_type ?? null,
        billing_address: customer?.billing_address ?? EMPTY_ADDRESS,
        email: customer?.email ?? '',
        payment_terms_days: String(customer?.payment_terms_days ?? options.default_payment_terms_days),
        irpf_applies: customer?.irpf_applies ?? false,
        surcharge_applies: customer?.surcharge_applies ?? false,
        default_vat_rate: customer?.default_vat_rate ?? null,
        notes: customer?.notes ?? '',
        contacts: customer?.contacts ?? ([] as ContactForm[]),
    });
    const errors = form.errors as Record<string, string>;
    const isBusiness = form.data.kind === 'business';
    const address = form.data.billing_address;

    const customerUrl = customer ? `${CUSTOMERS_URL}/${customer.id}` : null;

    function submit(force = false) {
        const url = customerUrl ?? CUSTOMERS_URL;
        const target = force ? `${url}?force=1` : url;
        // Si vuelve con el aviso de duplicado, el formulario conserva lo escrito.
        const visit = { preserveScroll: true, preserveState: true };

        if (customerUrl) {
            form.put(target, visit);
        } else {
            form.post(target, visit);
        }
    }

    function setAddress(changes: Partial<Address>) {
        form.setData('billing_address', { ...address, ...changes });
    }

    function setContact(index: number, changes: Partial<ContactForm>) {
        form.setData(
            'contacts',
            form.data.contacts.map((contact, i) => {
                if (changes.is_default) {
                    return i === index ? { ...contact, ...changes } : { ...contact, is_default: false };
                }

                return i === index ? { ...contact, ...changes } : contact;
            }),
        );
    }

    const title = isNew ? 'Nuevo cliente' : form.data.trade_name || form.data.legal_name || 'Cliente';

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
                className="mx-auto max-w-4xl"
            >
                <PageHeader
                    title={title}
                    description={isNew ? 'Los datos con * son obligatorios para facturar.' : 'Los cambios no afectan a las facturas ya emitidas.'}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={customerUrl ?? CUSTOMERS_URL}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Save aria-hidden />
                                {form.processing ? 'Guardando…' : 'Guardar'}
                            </Button>
                        </>
                    }
                />

                {duplicate ? (
                    <Alert className="mb-6 border-warning/30 bg-warning-soft text-warning-strong">
                        <TriangleAlert aria-hidden />
                        <AlertTitle>
                            Ya tienes un cliente con el NIF {duplicate.tax_id}: {duplicate.legal_name}.
                        </AlertTitle>
                        <AlertDescription className="text-current">
                            <p>Puede ser el mismo cliente. Si es otro (por ejemplo, otra sede de la misma empresa), guárdalo igualmente.</p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" asChild>
                                    <Link href={`${CUSTOMERS_URL}/${duplicate.id}`}>Abrir el existente</Link>
                                </Button>
                                <Button type="button" size="sm" disabled={form.processing} onClick={() => submit(true)}>
                                    Guardar de todos modos
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <FormSection title="Datos del cliente" description="Tal como deben salir en la factura.">
                    <fieldset className="mb-5">
                        <legend className="mb-1.5 text-sm font-medium">Tipo de cliente</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {options.kinds.map((kind) => {
                                const value = kind.value as CustomerKind;
                                const Icon = KIND_ICONS[value];
                                const selected = form.data.kind === value;

                                return (
                                    <button
                                        key={value}
                                        type="button"
                                        aria-pressed={selected}
                                        onClick={() => form.setData('kind', value)}
                                        className={cn(
                                            'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors',
                                            selected ? 'border-primary bg-accent' : 'hover:bg-muted/60',
                                        )}
                                    >
                                        <Icon className={cn('mt-0.5 size-4 shrink-0', selected ? 'text-primary' : 'text-muted-foreground')} />
                                        <span>
                                            <span className="block text-sm font-medium">{kind.label}</span>
                                            <span className="block text-xs text-muted-foreground">{KIND_HINTS[value]}</span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field id="legal_name" label={isBusiness ? 'Razón social o nombre' : 'Nombre y apellidos'} required error={errors.legal_name}>
                            <Input id="legal_name" value={form.data.legal_name} onChange={(event) => form.setData('legal_name', event.target.value)} />
                        </Field>
                        <Field id="trade_name" label="Nombre comercial" hint="Opcional. Es el que se ve en los listados." error={errors.trade_name}>
                            <Input id="trade_name" value={form.data.trade_name} onChange={(event) => form.setData('trade_name', event.target.value)} />
                        </Field>
                        <Field id="tax_id" label="NIF" required={isBusiness} hint="NIF, NIE, CIF o número de IVA europeo (p. ej. DE123456789)." error={errors.tax_id}>
                            <Input
                                id="tax_id"
                                value={form.data.tax_id}
                                onChange={(event) => form.setData('tax_id', event.target.value.toUpperCase())}
                                autoComplete="off"
                            />
                        </Field>
                        <Field id="tax_id_type" label="Tipo de identificador" hint="Se detecta solo; cámbialo para clientes de fuera de la UE." error={errors.tax_id_type}>
                            <Select value={form.data.tax_id_type ?? NONE} onValueChange={(value) => form.setData('tax_id_type', value === NONE ? null : value)}>
                                <SelectTrigger id="tax_id_type" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Detectar automáticamente</SelectItem>
                                    {options.tax_id_types.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field id="email" label="Email de facturación" hint="Si añades contactos, se usa el del contacto principal." error={errors.email} className="md:col-span-2">
                            <Input id="email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                        </Field>
                    </div>
                </FormSection>

                <FormSection title="Dirección de facturación" description={isBusiness ? 'Obligatoria para una empresa.' : 'Opcional para un particular.'}>
                    <div className="grid gap-4 md:grid-cols-6">
                        <Field id="street" label="Dirección" required={isBusiness} error={errors['billing_address.street']} className="md:col-span-6">
                            <Input id="street" value={address.street} onChange={(event) => setAddress({ street: event.target.value })} autoComplete="street-address" />
                        </Field>
                        <Field id="postal_code" label="Código postal" required={isBusiness} error={errors['billing_address.postal_code']} className="md:col-span-2">
                            <Input id="postal_code" value={address.postal_code} onChange={(event) => setAddress({ postal_code: event.target.value })} />
                        </Field>
                        <Field id="city" label="Población" required={isBusiness} error={errors['billing_address.city']} className="md:col-span-2">
                            <Input id="city" value={address.city} onChange={(event) => setAddress({ city: event.target.value })} />
                        </Field>
                        <Field id="province" label="Provincia" error={errors['billing_address.province']} className="md:col-span-1">
                            <Input id="province" value={address.province} onChange={(event) => setAddress({ province: event.target.value })} />
                        </Field>
                        <Field id="country" label="País" hint="ES, FR, DE…" error={errors['billing_address.country']} className="md:col-span-1">
                            <Input
                                id="country"
                                value={address.country}
                                maxLength={2}
                                onChange={(event) => setAddress({ country: event.target.value.toUpperCase() })}
                            />
                        </Field>
                    </div>
                </FormSection>

                <FormSection title="Condiciones de facturación" description="Lo que propone cada factura nueva a este cliente.">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field id="payment_terms_days" label="Plazo de pago (días)" required error={errors.payment_terms_days}>
                            <Input
                                id="payment_terms_days"
                                type="number"
                                min={0}
                                inputMode="numeric"
                                value={form.data.payment_terms_days}
                                onChange={(event) => form.setData('payment_terms_days', event.target.value)}
                            />
                        </Field>
                        <Field id="default_vat_rate" label="IVA por defecto" error={errors.default_vat_rate}>
                            <Select value={form.data.default_vat_rate ?? NONE} onValueChange={(value) => form.setData('default_vat_rate', value === NONE ? null : value)}>
                                <SelectTrigger id="default_vat_rate" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>El general</SelectItem>
                                    {invoiceConfig.vatRates.map((rate) => (
                                        <SelectItem key={rate} value={rate}>
                                            {decimalForInput(rate)} %
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <CheckboxField
                            id="irpf_applies"
                            label="Aplicar retención de IRPF"
                            hint={isBusiness ? 'Si tu actividad es profesional y este cliente es empresa o profesional.' : 'A un particular nunca se le aplica.'}
                            checked={form.data.irpf_applies}
                            disabled={!isBusiness}
                            onChange={(checked) => form.setData('irpf_applies', checked)}
                        />
                        <CheckboxField
                            id="surcharge_applies"
                            label="Recargo de equivalencia"
                            hint="Solo si el cliente es un comercio minorista en este régimen."
                            checked={form.data.surcharge_applies}
                            onChange={(checked) => form.setData('surcharge_applies', checked)}
                        />
                    </div>
                </FormSection>

                <FormSection
                    title="Contactos"
                    description="Personas a las que enviar los documentos. El principal recibe las facturas."
                    actions={
                        form.data.contacts.length < options.max_contacts ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => form.setData('contacts', [...form.data.contacts, { ...EMPTY_CONTACT, is_default: form.data.contacts.length === 0 }])}
                            >
                                <Plus aria-hidden />
                                Añadir contacto
                            </Button>
                        ) : null
                    }
                >
                    {form.data.contacts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Sin contactos: los documentos se envían al email de facturación.</p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {form.data.contacts.map((contact, index) => (
                                <li key={index} className="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_1fr_10rem_auto] md:items-end">
                                    <Field id={`contact-${index}-name`} label="Nombre" required error={errors[`contacts.${index}.name`]}>
                                        <Input
                                            id={`contact-${index}-name`}
                                            value={contact.name}
                                            onChange={(event) => setContact(index, { name: event.target.value })}
                                        />
                                    </Field>
                                    <Field id={`contact-${index}-email`} label="Email" required error={errors[`contacts.${index}.email`]}>
                                        <Input
                                            id={`contact-${index}-email`}
                                            type="email"
                                            value={contact.email}
                                            onChange={(event) => setContact(index, { email: event.target.value })}
                                        />
                                    </Field>
                                    <Field id={`contact-${index}-phone`} label="Teléfono" error={errors[`contacts.${index}.phone`]}>
                                        <Input
                                            id={`contact-${index}-phone`}
                                            value={contact.phone}
                                            onChange={(event) => setContact(index, { phone: event.target.value })}
                                        />
                                    </Field>
                                    <div className="flex items-center gap-3 md:pb-1.5">
                                        <label className="flex items-center gap-2 text-sm whitespace-nowrap">
                                            <input
                                                type="radio"
                                                name="default_contact"
                                                checked={contact.is_default}
                                                onChange={() => setContact(index, { is_default: true })}
                                                className="accent-primary"
                                            />
                                            Principal
                                        </label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Quitar contacto"
                                            title="Quitar contacto"
                                            className="size-8 text-muted-foreground"
                                            onClick={() => form.setData('contacts', form.data.contacts.filter((_, i) => i !== index))}
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </FormSection>

                <FormSection title="Notas" description="Solo para ti: no salen en ningún documento.">
                    <Textarea
                        id="notes"
                        aria-label="Notas"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        rows={4}
                        placeholder="Horario, persona de contacto en administración, condiciones acordadas…"
                    />
                    {errors.notes ? <p className="mt-1 text-xs text-destructive">{errors.notes}</p> : null}
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

function CheckboxField({
    id,
    label,
    hint,
    checked,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className={cn('flex items-start gap-3 rounded-lg border p-3', disabled && 'opacity-60')}>
            <Checkbox id={id} checked={checked && !disabled} disabled={disabled} onCheckedChange={(value) => onChange(value === true)} className="mt-0.5" />
            <div>
                <Label htmlFor={id}>{label}</Label>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
        </div>
    );
}
