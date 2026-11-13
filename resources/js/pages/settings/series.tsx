import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, PowerOff, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Field } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { SettingsTabs } from '@/components/SettingsTabs';
import { StatusPill } from '@/components/StatusPill';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { DocumentTypeValue } from '@/types/documents';

interface SeriesRow {
    id: string;
    document_type: DocumentTypeValue;
    code: string;
    prefix: string;
    padding: number;
    next_number: number;
    next_full_number: string;
    resets_yearly: boolean;
    is_default: boolean;
    is_active: boolean;
    documents_count: number;
    has_issued: boolean;
}

interface Limits {
    min_padding: number;
    max_padding: number;
    default_padding: number;
    max_code_length: number;
    max_prefix_length: number;
}

interface Props {
    series: SeriesRow[];
    types: { value: DocumentTypeValue; label: string }[];
    limits: Limits;
    year: number;
}

const SERIES_URL = '/settings/series';

const DEFAULT_TYPE: DocumentTypeValue = 'invoice';

type SeriesForm = {
    document_type: DocumentTypeValue;
    code: string;
    prefix: string;
    padding: string;
    next_number: string;
    resets_yearly: boolean;
    is_default: boolean;
    is_active: boolean;
};

/** Separador entre prefijo y número; el mismo que `DocumentNumber::SEPARATOR`. */
const NUMBER_SEPARATOR = '-';

/** Número de ejemplo tal como saldrá: F2026-0001. */
function previewNumber(prefix: string, nextNumber: number, padding: number, resetsYearly: boolean, year: number): string {
    const safeNumber = Number.isFinite(nextNumber) && nextNumber > 0 ? nextNumber : 1;

    return `${prefix.toUpperCase()}${resetsYearly ? year : ''}${NUMBER_SEPARATOR}${String(safeNumber).padStart(padding, '0')}`;
}

/**
 * Ajustes > Series: cómo se numeran las facturas, rectificativas y
 * presupuestos. Con documentos emitidos, el formato de una serie queda fijado
 * y su contador solo puede subir; las que tienen documentos no se borran.
 */
export default function SeriesSettings({ series, types, limits, year }: Props) {
    const [editing, setEditing] = useState<SeriesRow | 'new' | null>(null);

    function toggle(item: SeriesRow) {
        router.post(`${SERIES_URL}/${item.id}/toggle`, {}, { preserveScroll: true });
    }

    function remove(item: SeriesRow) {
        if (window.confirm(`¿Borrar la serie ${item.code}? No se puede deshacer.`)) {
            router.delete(`${SERIES_URL}/${item.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Series" />

            <div className="mx-auto max-w-4xl">
                <SettingsTabs />

                <PageHeader
                    title="Series de numeración"
                    description="Cada serie lleva su propia numeración correlativa. La serie por defecto es la que se usa si no eliges otra al emitir."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden />
                            Nueva serie
                        </Button>
                    }
                />

                {types.map((type) => {
                    const rows = series.filter((item) => item.document_type === type.value);

                    return (
                        <section key={type.value} className="mb-6">
                            <h2 className="mb-2 font-semibold">{type.label}</h2>
                            {rows.length === 0 ? (
                                <p className="rounded-lg border border-dashed bg-card p-6 text-center text-sm text-muted-foreground">No hay series de este tipo.</p>
                            ) : (
                                <div className="overflow-hidden rounded-lg border bg-card">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="hover:bg-transparent">
                                                <HeadCell>Serie</HeadCell>
                                                <HeadCell>Próximo número</HeadCell>
                                                <HeadCell className="hidden text-right sm:table-cell">Documentos</HeadCell>
                                                <HeadCell className="w-32">
                                                    <span className="sr-only">Acciones</span>
                                                </HeadCell>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {rows.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell className="px-3 py-3 sm:px-4">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="font-medium">{item.code}</span>
                                                            {item.is_default ? <StatusPill tone="info" label="Por defecto" /> : null}
                                                            {!item.is_active ? <StatusPill tone="neutral" label="Desactivada" /> : null}
                                                        </div>
                                                        <span className="block text-xs text-muted-foreground">
                                                            Prefijo {item.prefix}
                                                            {item.resets_yearly ? ', vuelve al 1 cada año' : ', sin reinicio anual'}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className={cn('px-3 py-3 tabular-nums sm:px-4', !item.is_active && 'text-muted-foreground')}>
                                                        {item.next_full_number}
                                                    </TableCell>
                                                    <TableCell className="hidden px-3 py-3 text-right text-muted-foreground tabular-nums sm:table-cell sm:px-4">
                                                        {item.documents_count}
                                                    </TableCell>
                                                    <TableCell className="px-2 py-3 text-right whitespace-nowrap">
                                                        <IconButton label={`Editar ${item.code}`} onClick={() => setEditing(item)}>
                                                            <Pencil aria-hidden />
                                                        </IconButton>
                                                        {!item.is_default ? (
                                                            <IconButton label={item.is_active ? `Desactivar ${item.code}` : `Activar ${item.code}`} onClick={() => toggle(item)}>
                                                                {item.is_active ? <PowerOff aria-hidden /> : <Power aria-hidden />}
                                                            </IconButton>
                                                        ) : null}
                                                        {!item.is_default && item.documents_count === 0 ? (
                                                            <IconButton label={`Borrar ${item.code}`} onClick={() => remove(item)} danger>
                                                                <Trash2 aria-hidden />
                                                            </IconButton>
                                                        ) : null}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}
                        </section>
                    );
                })}
            </div>

            {editing ? (
                <SeriesDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    series={editing === 'new' ? null : editing}
                    types={types}
                    limits={limits}
                    year={year}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function SeriesDialog({
    series,
    types,
    limits,
    year,
    onClose,
}: {
    series: SeriesRow | null;
    types: Props['types'];
    limits: Limits;
    year: number;
    onClose: () => void;
}) {
    const locked = series?.has_issued ?? false;
    const form = useForm<SeriesForm>({
        document_type: series?.document_type ?? types[0]?.value ?? DEFAULT_TYPE,
        code: series?.code ?? '',
        prefix: series?.prefix ?? '',
        padding: String(series?.padding ?? limits.default_padding),
        next_number: String(series?.next_number ?? 1),
        resets_yearly: series?.resets_yearly ?? true,
        is_default: series?.is_default ?? false,
        is_active: series?.is_active ?? true,
    });
    const errors = form.errors as Record<string, string>;
    const preview = previewNumber(form.data.prefix || form.data.code, Number(form.data.next_number), Number(form.data.padding), form.data.resets_yearly, year);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, prefix: data.prefix || data.code }));

        const options = { preserveScroll: true, onSuccess: onClose };

        if (series) {
            form.put(`${SERIES_URL}/${series.id}`, options);
        } else {
            form.post(SERIES_URL, options);
        }
    }

    return (
        <Dialog open onOpenChange={(open) => (!open ? onClose() : undefined)}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{series ? `Serie ${series.code}` : 'Nueva serie'}</DialogTitle>
                        <DialogDescription>
                            {locked
                                ? 'Ya tiene documentos emitidos: su formato no se puede cambiar y el siguiente número solo puede subir.'
                                : 'Así se numerarán los documentos que emitas en esta serie.'}
                        </DialogDescription>
                    </DialogHeader>

                    {errors.domain ? (
                        <Alert variant="destructive">
                            <AlertTitle>{errors.domain}</AlertTitle>
                        </Alert>
                    ) : null}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id="series-type" label="Tipo de documento" required error={errors.document_type} className="sm:col-span-2">
                            <Select
                                value={form.data.document_type}
                                onValueChange={(value) => form.setData('document_type', value as DocumentTypeValue)}
                                disabled={(series?.documents_count ?? 0) > 0}
                            >
                                <SelectTrigger id="series-type" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field id="series-code" label="Código" required hint="Para reconocerla, por ejemplo TALLER." error={errors.code}>
                            <Input
                                id="series-code"
                                value={form.data.code}
                                maxLength={limits.max_code_length}
                                disabled={locked}
                                onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                            />
                        </Field>

                        <Field id="series-prefix" label="Prefijo" hint="Lo que va delante del número. Si lo dejas vacío, el código." error={errors.prefix}>
                            <Input
                                id="series-prefix"
                                value={form.data.prefix}
                                maxLength={limits.max_prefix_length}
                                disabled={locked}
                                placeholder={form.data.code}
                                onChange={(event) => form.setData('prefix', event.target.value.toUpperCase())}
                            />
                        </Field>

                        <Field id="series-next" label="Siguiente número" required error={errors.next_number}>
                            <Input
                                id="series-next"
                                type="number"
                                inputMode="numeric"
                                min={locked && series ? series.next_number : 1}
                                value={form.data.next_number}
                                onChange={(event) => form.setData('next_number', event.target.value)}
                            />
                        </Field>

                        <Field id="series-padding" label="Dígitos" required hint="Con 4, el 7 sale como 0007." error={errors.padding}>
                            <Input
                                id="series-padding"
                                type="number"
                                inputMode="numeric"
                                min={limits.min_padding}
                                max={limits.max_padding}
                                disabled={locked}
                                value={form.data.padding}
                                onChange={(event) => form.setData('padding', event.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-2">
                        <CheckRow
                            id="series-yearly"
                            checked={form.data.resets_yearly}
                            disabled={locked}
                            onChange={(value) => form.setData('resets_yearly', value)}
                            label="Volver al 1 cada año"
                            hint="El año va en el número: F2026-0001, F2027-0001…"
                        />
                        <CheckRow
                            id="series-default"
                            checked={form.data.is_default}
                            disabled={series?.is_default ?? false}
                            onChange={(value) => form.setData('is_default', value)}
                            label="Serie por defecto"
                            hint={series?.is_default ? 'Para cambiarla, marca otra serie como serie por defecto.' : 'Sustituye a la serie por defecto actual de este tipo.'}
                        />
                    </div>

                    <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                        El próximo documento será <span className="font-semibold tabular-nums">{preview}</span>
                    </p>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save aria-hidden />
                            {form.processing ? 'Guardando…' : 'Guardar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CheckRow({
    id,
    checked,
    disabled,
    onChange,
    label,
    hint,
}: {
    id: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (value: boolean) => void;
    label: string;
    hint: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border p-3">
            <Checkbox id={id} checked={checked} disabled={disabled} onCheckedChange={(value) => onChange(value === true)} className="mt-0.5" />
            <div>
                <Label htmlFor={id}>{label}</Label>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
        </div>
    );
}

function IconButton({ label, onClick, danger, children }: { label: string; onClick: () => void; danger?: boolean; children: ReactNode }) {
    return (
        <Button
            variant="ghost"
            size="icon"
            className={cn('size-8 text-muted-foreground', danger && 'hover:text-destructive')}
            aria-label={label}
            title={label}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function HeadCell({ className, children }: { className?: string; children: ReactNode }) {
    return <TableHead className={cn('h-10 px-3 text-xs font-medium text-muted-foreground sm:px-4', className)}>{children}</TableHead>;
}
