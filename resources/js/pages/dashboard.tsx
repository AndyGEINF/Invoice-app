import { Head, Link } from '@inertiajs/react';
import { ChevronDown, CircleAlert, Clock, FileCheck2, FilePen, FilePlus, Plus, TrendingDown, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';

import { MonthlyBillingChart } from '@/components/dashboard/MonthlyBillingChart';
import type { MonthlyBilling } from '@/components/dashboard/MonthlyBillingChart';
import { RowStatus } from '@/components/documents/StatusBadge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { DOCUMENT_SEGMENTS } from '@/lib/documents';
import { NARROW_QUERY, useMediaQuery } from '@/lib/media';
import { monthName, monthWithYear } from '@/lib/months';
import { cn } from '@/lib/utils';
import type { DocumentRow, DocumentTypeValue } from '@/types/documents';

interface Props {
    stats: {
        issued_this_month_count: number;
        issued_this_month_total_formatted: string;
        previous_month_total_formatted: string;
        change_percent: number | null;
        pending_count: number;
        pending_total_formatted: string;
        overdue_count: number;
        overdue_total_formatted: string;
        outstanding_total_formatted: string;
        overdue_share_percent: number;
    };
    monthly: MonthlyBilling[];
    quotes: { open_count: number; open_total_formatted: string };
    attention: AttentionItem[];
    recent: DocumentRow[];
    issuer_incomplete: boolean;
}

type AttentionKind = 'overdue_invoices' | 'accepted_quotes' | 'expiring_quotes' | 'draft_invoices';

interface AttentionItem {
    kind: AttentionKind;
    count: number;
    total_formatted: string;
}

/** Singular o plural según cuántos son: plural(1, 'factura vencida', 'facturas vencidas'). */
function plural(count: number, one: string, many: string): string {
    return count === 1 ? `1 ${one}` : `${count} ${many}`;
}

/** Cada aviso dice qué pasa, por qué importa y adónde ir para resolverlo. */
const ATTENTION_COPY: Record<
    AttentionKind,
    { icon: ComponentType<{ className?: string }>; tone: string; title: (count: number) => string; detail: string; href: string; action: string }
> = {
    overdue_invoices: {
        icon: CircleAlert,
        tone: 'bg-danger-soft text-danger-strong',
        title: (count) => plural(count, 'factura vencida', 'facturas vencidas'),
        detail: 'Ha pasado su fecha de vencimiento y no están marcadas como cobradas.',
        href: '/invoices?payment_status=overdue',
        action: 'Ver vencidas',
    },
    accepted_quotes: {
        icon: FileCheck2,
        tone: 'bg-success-soft text-success-strong',
        title: (count) => plural(count, 'presupuesto aceptado sin facturar', 'presupuestos aceptados sin facturar'),
        detail: 'El cliente ya dijo que sí: conviértelos en factura.',
        href: '/quotes?status=accepted',
        action: 'Ver aceptados',
    },
    expiring_quotes: {
        icon: Clock,
        tone: 'bg-warning-soft text-warning-strong',
        title: (count) => plural(count, 'presupuesto caduca esta semana', 'presupuestos caducan esta semana'),
        detail: 'Si el cliente aún no ha respondido, es buen momento para recordárselo.',
        href: '/quotes?status=sent',
        action: 'Ver enviados',
    },
    draft_invoices: {
        icon: FilePen,
        tone: 'bg-muted text-muted-foreground',
        title: (count) => plural(count, 'factura en borrador', 'facturas en borrador'),
        detail: 'No tienen número ni validez fiscal hasta que las emitas.',
        href: '/invoices?status=draft',
        action: 'Ver borradores',
    },
};

const TYPE_LABELS: Record<DocumentTypeValue, string> = {
    quote: 'Presupuesto',
    invoice: 'Factura',
    credit_note: 'Rectificativa',
};

/**
 * Inicio: cuánto se ha facturado este mes y cómo va frente a los anteriores,
 * qué queda por cobrar, qué pide atención y lo último que se ha movido.
 */
export default function Dashboard({ stats, monthly, quotes, attention, recent, issuer_incomplete }: Props) {
    const current = monthly[monthly.length - 1];
    const currentMonth = current ? monthName(current.month) : '';

    return (
        <div className="font-dashboard">
            <Head title="Inicio" />

            <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight first-letter:uppercase">{currentMonth}</h1>
                    <p className="text-sm text-muted-foreground">Cómo va tu facturación este mes.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/quotes/create">
                            <FilePlus aria-hidden />
                            Nuevo presupuesto
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href="/invoices/create">
                            <Plus aria-hidden />
                            Nueva factura
                        </Link>
                    </Button>
                </div>
            </header>

            {issuer_incomplete ? (
                <section className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-card px-5 py-4">
                    <div>
                        <p className="font-medium">Faltan los datos de tu empresa</p>
                        <p className="text-sm text-muted-foreground">Sin tu nombre, NIF, dirección y logotipo no se puede emitir ninguna factura.</p>
                    </div>
                    <Button asChild>
                        <Link href="/settings/issuer">Completar datos</Link>
                    </Button>
                </section>
            ) : null}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_24rem]">
                <Billed stats={stats} monthly={monthly} />

                <div className="flex flex-col gap-6">
                    <Outstanding stats={stats} />

                    <section aria-labelledby="quotes-title" className="rounded-lg border bg-card p-6">
                        <h2 id="quotes-title" className="text-sm text-muted-foreground">
                            Presupuestos abiertos
                        </h2>
                        <p className="mt-1 text-3xl font-semibold tracking-tight">{quotes.open_total_formatted}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {quotes.open_count === 0
                                ? 'Ninguno pendiente de respuesta o de facturar.'
                                : `${plural(quotes.open_count, 'presupuesto enviado o aceptado', 'presupuestos enviados o aceptados')}, aún sin facturar.`}
                        </p>
                        <Link href="/quotes" className="mt-4 inline-block text-sm text-primary hover:underline">
                            Ver presupuestos
                        </Link>
                    </section>
                </div>
            </div>

            <Attention items={attention} />

            <section aria-labelledby="recent-title" className="mt-10">
                <div className="mb-3 flex items-baseline justify-between gap-4">
                    <h2 id="recent-title" className="text-lg font-semibold tracking-tight">
                        Actividad reciente
                    </h2>
                    <Link href="/invoices" className="text-sm text-primary hover:underline">
                        Ver todas las facturas
                    </Link>
                </div>

                {recent.length === 0 ? (
                    <p className="rounded-lg border border-dashed bg-card p-10 text-center text-sm text-muted-foreground">
                        Aún no hay documentos. Empieza por un presupuesto o una factura.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="px-5 py-3 font-normal">Documento</th>
                                    <th className="hidden px-5 py-3 font-normal sm:table-cell">Cliente</th>
                                    <th className="hidden px-5 py-3 text-right font-normal md:table-cell">Fecha</th>
                                    <th className="px-5 py-3 text-right font-normal">Importe</th>
                                    <th className="hidden px-5 py-3 text-right font-normal sm:table-cell">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.map((row) => (
                                    <tr key={row.id} className="border-b transition-colors last:border-b-0 hover:bg-muted/40">
                                        <td className="px-5 py-3">
                                            <Link href={`/${DOCUMENT_SEGMENTS[row.type]}/${row.id}`} className="font-medium text-foreground hover:text-primary hover:underline">
                                                {row.full_number ?? (row.status === 'draft' ? 'Borrador' : 'Sin número')}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">{TYPE_LABELS[row.type]}</span>
                                        </td>
                                        <td className="hidden max-w-64 truncate px-5 py-3 sm:table-cell">{row.customer_name ?? 'Sin cliente'}</td>
                                        <td className="hidden px-5 py-3 text-right text-muted-foreground tabular-nums md:table-cell">
                                            {formatDate(row.issue_date) || '—'}
                                        </td>
                                        <td className="px-5 py-3 text-right font-medium tabular-nums">{row.total_formatted}</td>
                                        <td className="hidden px-5 py-3 text-right sm:table-cell">
                                            <RowStatus
                                                status={row.status}
                                                statusLabel={row.status_label}
                                                paymentStatus={row.payment_status}
                                                paymentStatusLabel={row.payment_status_label}
                                                isExpired={row.is_expired}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

const PERCENT = 100;

interface BilledSummary {
    total_formatted: string;
    invoices: number;
    change_percent: number | null;
}

/**
 * Lo que manda: lo facturado en un mes y su evolución. En pantalla ancha es
 * siempre el mes actual, con el gráfico de los doce meses debajo. En móvil el
 * gráfico no cabe: se quita y el mes se elige con un selector.
 */
function Billed({ stats, monthly }: { stats: Props['stats']; monthly: MonthlyBilling[] }) {
    const narrow = useMediaQuery(NARROW_QUERY);
    const lastIndex = monthly.length - 1;
    const [selected, setSelected] = useState(lastIndex);
    const index = narrow ? selected : lastIndex;
    const month = monthly[index];
    const previous = monthly[index - 1];
    const isCurrent = index === lastIndex;

    // El mes actual viene calculado del servidor; los anteriores, de la serie mensual.
    const summary: BilledSummary = isCurrent
        ? { total_formatted: stats.issued_this_month_total_formatted, invoices: stats.issued_this_month_count, change_percent: stats.change_percent }
        : {
              total_formatted: month?.total_formatted ?? '',
              invoices: month?.invoices ?? 0,
              change_percent: month && previous && previous.total > 0 ? Math.trunc(((month.total - previous.total) * PERCENT) / previous.total) : null,
          };

    return (
        <section aria-labelledby="billed-title" className="rounded-lg border bg-card p-6 md:p-8">
            {narrow ? (
                <label id="billed-title" className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                    Facturado en
                    <span className="relative">
                        <select
                            value={selected}
                            onChange={(event) => setSelected(Number(event.target.value))}
                            className="h-8 appearance-none rounded-md border bg-card pr-8 pl-2.5 font-medium text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {monthly
                                .map((option, optionIndex) => (
                                    <option key={option.month} value={optionIndex}>
                                        {monthWithYear(option.month)}
                                    </option>
                                ))
                                .reverse()}
                        </select>
                        <ChevronDown aria-hidden className="pointer-events-none absolute top-1/2 right-2 size-4 -translate-y-1/2" />
                    </span>
                </label>
            ) : (
                <p id="billed-title" className="text-sm text-muted-foreground">
                    Facturado en {month ? monthName(month.month) : ''}
                </p>
            )}
            <p className="mt-1 text-5xl font-semibold tracking-tight md:text-6xl">{summary.total_formatted}</p>
            {previous ? (
                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <Change percent={summary.change_percent} />
                    <span className="text-muted-foreground">
                        frente a {isCurrent ? stats.previous_month_total_formatted : previous.total_formatted} en {monthName(previous.month)}
                    </span>
                </div>
            ) : null}
            <p className="mt-1 text-sm text-muted-foreground">
                {plural(summary.invoices, 'factura emitida', 'facturas emitidas')} {isCurrent ? 'este mes' : `en ${month ? monthName(month.month) : ''}`}
            </p>

            {narrow ? null : (
                <div className="mt-8">
                    <MonthlyBillingChart months={monthly} />
                </div>
            )}
        </section>
    );
}

/** Variación frente al mes anterior. Subir es bueno: verde con flecha arriba; bajar, rojo con flecha abajo. */
function Change({ percent }: { percent: number | null }) {
    if (percent === null) {
        return null;
    }

    const up = percent >= 0;
    const Icon = up ? TrendingUp : TrendingDown;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-medium',
                up ? 'bg-success-soft text-success-strong' : 'bg-danger-soft text-danger-strong',
            )}
        >
            <Icon className="size-3.5" aria-hidden />
            {up ? '+' : '−'}
            {Math.abs(percent)} %
        </span>
    );
}

/**
 * Por cobrar: todo lo emitido y sin cobrar, partido en lo que aún está en plazo
 * y lo vencido. Una sola barra de dos tramos, cada uno con su etiqueta.
 */
function Outstanding({ stats }: { stats: Props['stats'] }) {
    const overdueShare = stats.overdue_share_percent;
    const nothingOutstanding = stats.pending_count + stats.overdue_count === 0;

    return (
        <section aria-labelledby="outstanding-title" className="rounded-lg border bg-card p-6">
            <h2 id="outstanding-title" className="text-sm text-muted-foreground">
                Por cobrar
            </h2>
            <p className="mt-1 text-3xl font-semibold tracking-tight">{stats.outstanding_total_formatted}</p>

            {nothingOutstanding ? (
                <p className="mt-1 text-sm text-muted-foreground">Todo lo emitido está cobrado.</p>
            ) : (
                <>
                    <div
                        className="mt-4 flex h-2 gap-0.5 overflow-hidden rounded-full"
                        role="img"
                        aria-label={`${stats.pending_total_formatted} en plazo y ${stats.overdue_total_formatted} vencido`}
                    >
                        {overdueShare < 100 ? <span className="h-full bg-warning" style={{ width: `${100 - overdueShare}%` }} /> : null}
                        {overdueShare > 0 ? <span className="h-full bg-danger" style={{ width: `${overdueShare}%` }} /> : null}
                    </div>
                    <dl className="mt-4 flex flex-col gap-2 text-sm">
                        <Legend swatch="bg-warning" label="En plazo" value={stats.pending_total_formatted} detail={plural(stats.pending_count, 'factura', 'facturas')} />
                        <Legend swatch="bg-danger" label="Vencido" value={stats.overdue_total_formatted} detail={plural(stats.overdue_count, 'factura', 'facturas')} />
                    </dl>
                </>
            )}
        </section>
    );
}

function Legend({ swatch, label, value, detail }: { swatch: string; label: string; value: string; detail: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="flex items-center gap-2">
                <span aria-hidden className={cn('size-2 rounded-full', swatch)} />
                {label}
                <span className="text-muted-foreground">({detail})</span>
            </dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

/** Lo que pide hacer algo hoy. Si no hay nada, lo dice en una línea y no ocupa más. */
function Attention({ items }: { items: AttentionItem[] }) {
    if (items.length === 0) {
        return <p className="mt-10 text-sm text-muted-foreground">Todo al día: no hay facturas vencidas ni presupuestos pendientes de facturar.</p>;
    }

    return (
        <section aria-labelledby="attention-title" className="mt-10">
            <h2 id="attention-title" className="mb-3 text-lg font-semibold tracking-tight">
                Necesita tu atención
            </h2>
            <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                {items.map((item) => {
                    const copy = ATTENTION_COPY[item.kind];
                    const Icon = copy.icon;

                    return (
                        <AttentionRow key={item.kind} href={copy.href} action={copy.action}>
                            <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full', copy.tone)}>
                                <Icon className="size-4" aria-hidden />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block font-medium">{copy.title(item.count)}</span>
                                <span className="block text-sm text-muted-foreground">{copy.detail}</span>
                            </span>
                            <span className="hidden font-medium tabular-nums sm:block">{item.total_formatted}</span>
                        </AttentionRow>
                    );
                })}
            </ul>
        </section>
    );
}

function AttentionRow({ href, action, children }: { href: string; action: string; children: ReactNode }) {
    return (
        <li>
            <Link href={href} className="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/40">
                {children}
                <span className="shrink-0 text-sm text-primary">{action}</span>
            </Link>
        </li>
    );
}
