import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';
import type { PaperView } from '@/types/documents';

/**
 * El documento tal como sale en el PDF: mismo contenido y mismo orden.
 *
 * Emitido: se pinta entero con `paper` (formateado por el servidor). Borrador:
 * los huecos `customer`, `lines`, `totals`, `notes` y `dates` reciben los
 * editores y el resto (emisor, título) sigue saliendo de `paper`.
 */
export function DocumentPaper({
    paper,
    title,
    status,
    dates,
    customer,
    total,
    lines,
    totals,
    notes,
    className,
}: {
    paper: PaperView;
    title?: string;
    status?: ReactNode;
    dates?: ReactNode;
    customer?: ReactNode;
    total?: string;
    lines?: ReactNode;
    totals?: ReactNode;
    notes?: ReactNode;
    className?: string;
}) {
    const { issuer } = paper;

    return (
        <article className={cn('rounded-2xl border bg-card p-4 shadow-sm sm:p-6 md:p-10', className)}>
            <header className="flex flex-wrap items-start justify-between gap-6">
                <div className="flex min-w-0 items-start gap-4">
                    {issuer.logo ? (
                        <img src={issuer.logo} alt={`Logotipo de ${issuer.name}`} className="h-14 max-w-40 rounded-lg object-contain" />
                    ) : (
                        <span className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent text-xl font-semibold text-accent-foreground">
                            {issuer.name.charAt(0).toUpperCase() || '?'}
                        </span>
                    )}
                    <div className="min-w-0 text-sm">
                        <p className="text-base font-semibold">{issuer.name || 'Tu empresa'}</p>
                        {issuer.contact_name ? <p>{issuer.contact_name}</p> : null}
                        {issuer.tax_id ? <p className="text-muted-foreground">NIF {issuer.tax_id}</p> : null}
                        {issuer.address_lines.length > 0 ? <p className="text-muted-foreground">{issuer.address_lines.join(' · ')}</p> : null}
                        {issuer.contact_lines.length > 0 ? <p className="text-muted-foreground">{issuer.contact_lines.join(' · ')}</p> : null}
                    </div>
                </div>

                {/* ml-auto: si no cabe al lado del emisor y baja de línea, sigue pegado a la derecha. */}
                <div className="ml-auto text-right">
                    <p className="text-xl font-semibold tracking-tight">{title ?? paper.title}</p>
                    <div className="mt-1 flex justify-end">{status ?? (paper.number ? <p className="font-semibold">{paper.number}</p> : null)}</div>
                    <div className="mt-2 text-sm">
                        {dates ??
                            paper.dates.map((date) => (
                                <p key={date.label}>
                                    <span className="text-muted-foreground">{date.label}</span> <span className="tabular-nums">{date.value}</span>
                                </p>
                            ))}
                    </div>
                </div>
            </header>

            <hr className="my-6" />

            <section className="flex flex-wrap items-start justify-between gap-6">
                <div className="min-w-0 flex-1">
                    <p className="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">Cliente</p>
                    {customer ?? <PaperCustomer paper={paper} />}
                </div>
                <div className="text-right">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Total</p>
                    <p className="text-3xl font-semibold tracking-tight tabular-nums">{total ?? paper.total}</p>
                </div>
            </section>

            {paper.rectifies ? (
                <p className="mt-6 rounded-lg border-l-4 border-warning bg-warning-soft px-4 py-2 text-sm text-warning-strong">{paper.rectifies}</p>
            ) : null}

            <hr className="my-6" />

            <section aria-label="Líneas">{lines ?? <PaperLines paper={paper} />}</section>

            <hr className="my-6" />

            <section className="flex flex-col-reverse gap-6 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0 flex-1 text-sm">{notes ?? <PaperNotes paper={paper} />}</div>
                <div className="w-full md:w-80">{totals ?? <PaperTotals paper={paper} />}</div>
            </section>

            {paper.exemptions.length > 0 ? (
                <ul className="mt-6 list-disc pl-5 text-xs text-muted-foreground">
                    {paper.exemptions.map((exemption) => (
                        <li key={exemption}>{exemption}</li>
                    ))}
                </ul>
            ) : null}

            {paper.footer ? <p className="mt-8 border-t pt-4 text-xs whitespace-pre-line text-muted-foreground">{paper.footer}</p> : null}
        </article>
    );
}

function PaperCustomer({ paper }: { paper: PaperView }) {
    if (!paper.customer) {
        return <p className="text-sm text-muted-foreground">Sin cliente · factura simplificada</p>;
    }

    return (
        <div className="text-sm">
            <p className="text-base font-medium">{paper.customer.name}</p>
            {paper.customer.trade_name ? <p>{paper.customer.trade_name}</p> : null}
            {paper.customer.tax_id ? <p className="text-muted-foreground">NIF {paper.customer.tax_id}</p> : null}
            {paper.customer.address_lines.map((line) => (
                <p key={line} className="text-muted-foreground">
                    {line}
                </p>
            ))}
        </div>
    );
}

/** Dirección del cliente ya guardado: también la usa el editor bajo el selector. */
export function PaperCustomerAddress({ paper }: { paper: PaperView }) {
    if (!paper.customer) {
        return null;
    }

    return (
        <div className="mt-1 text-sm text-muted-foreground">
            {paper.customer.tax_id ? <p>NIF {paper.customer.tax_id}</p> : null}
            {paper.customer.address_lines.map((line) => (
                <p key={line}>{line}</p>
            ))}
        </div>
    );
}

function PaperLines({ paper }: { paper: PaperView }) {
    if (paper.lines.length === 0) {
        return <p className="text-sm text-muted-foreground">Sin líneas.</p>;
    }

    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="text-xs tracking-wide text-muted-foreground uppercase">
                    <th className="pb-3 text-left font-medium">Descripción</th>
                    <th className="pb-3 text-right font-medium">Cantidad</th>
                    <th className="hidden pb-3 text-right font-medium sm:table-cell">Precio</th>
                    <th className="hidden pb-3 text-right font-medium sm:table-cell">IVA</th>
                    <th className="pb-3 text-right font-medium">Total</th>
                </tr>
            </thead>
            <tbody>
                {paper.lines.map((line, index) => (
                    <tr key={index} className="border-t align-top">
                        <td className="py-3 pr-4">
                            <p className="font-medium whitespace-pre-line">{line.description}</p>
                            {line.discount ? <p className="text-xs text-muted-foreground">Descuento {line.discount}</p> : null}
                        </td>
                        <td className="py-3 text-right tabular-nums">
                            {line.quantity} <span className="text-muted-foreground">{line.unit}</span>
                        </td>
                        <td className="hidden py-3 text-right tabular-nums sm:table-cell">{line.unit_price}</td>
                        <td className="hidden py-3 text-right sm:table-cell">{line.vat}</td>
                        <td className="py-3 text-right font-semibold tabular-nums">{line.amount}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function PaperNotes({ paper }: { paper: PaperView }) {
    if (!paper.notes && !paper.globalDiscount) {
        return null;
    }

    return (
        <>
            {paper.globalDiscount ? <p className="mb-2 text-muted-foreground">Descuento global del {paper.globalDiscount} aplicado.</p> : null}
            {paper.notes ? (
                <>
                    <p className="font-medium">Información y detalles de pago</p>
                    <p className="whitespace-pre-line text-muted-foreground">{paper.notes}</p>
                </>
            ) : null}
        </>
    );
}

function PaperTotals({ paper }: { paper: PaperView }) {
    return (
        <dl className="text-sm">
            {paper.taxes.length > 0 ? (
                <div className="mb-3 flex flex-col gap-1 text-xs text-muted-foreground">
                    {paper.taxes.map((tax) => (
                        <div key={tax.label} className="flex justify-between gap-4">
                            <dt>
                                {tax.label} <span className="tabular-nums">sobre {tax.base}</span>
                            </dt>
                            <dd className="tabular-nums">{tax.amount}</dd>
                        </div>
                    ))}
                </div>
            ) : null}
            {paper.totals.map((row) => (
                <div key={row.label} className="flex justify-between gap-4 py-1">
                    <dt className="text-muted-foreground">{row.label}</dt>
                    <dd className="tabular-nums">{row.value}</dd>
                </div>
            ))}
            <div className="mt-2 flex justify-between gap-4 border-t pt-3 text-base font-semibold">
                <dt>Total</dt>
                <dd className="tabular-nums">{paper.total}</dd>
            </div>
        </dl>
    );
}
