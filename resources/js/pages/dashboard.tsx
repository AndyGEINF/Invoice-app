import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Plus } from 'lucide-react';

import { RowStatus } from '@/components/documents/StatusBadge';
import { PageHeader } from '@/components/PageHeader';
import { StatCard } from '@/components/StatCard';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { DOCUMENT_SEGMENTS } from '@/lib/documents';
import type { DocumentRow } from '@/types/documents';

interface Props {
    stats: {
        issued_this_month_count: number;
        issued_this_month_total_formatted: string;
        pending_count: number;
        pending_total_formatted: string;
        overdue_count: number;
        overdue_total_formatted: string;
    };
    recent: DocumentRow[];
    issuer_incomplete: boolean;
}

function invoicesLabel(count: number): string {
    return count === 1 ? '1 factura' : `${count} facturas`;
}

/** Inicio: cómo va el mes y lo último que se ha tocado. Los gráficos llegan con T113. */
export default function Dashboard({ stats, recent, issuer_incomplete }: Props) {
    const { issuer } = usePage().props;
    const greeting = issuer.name ? `Hola, ${issuer.name.split(' ')[0]}` : 'Inicio';

    return (
        <>
            <Head title="Inicio" />

            <PageHeader
                title={greeting}
                description="Resumen de tu facturación."
                actions={
                    <Button asChild>
                        <Link href="/invoices/create">
                            <Plus aria-hidden />
                            Nueva factura
                        </Link>
                    </Button>
                }
            />

            {issuer_incomplete ? (
                <section className="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-primary/20 bg-accent p-5">
                    <span className="flex size-10 items-center justify-center rounded-full bg-card text-primary">
                        <Building2 className="size-5" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="font-semibold text-accent-foreground">Empieza por los datos de tu empresa</p>
                        <p className="text-sm text-muted-foreground">Tu nombre, NIF, dirección y logotipo aparecen en todas las facturas.</p>
                    </div>
                    <Button asChild>
                        <Link href="/settings/issuer">Completar datos</Link>
                    </Button>
                </section>
            ) : null}

            <section aria-label="Resumen" className="mb-6 grid gap-3 sm:grid-cols-3">
                <StatCard label="Facturado este mes" value={stats.issued_this_month_total_formatted} tone="info" hint={invoicesLabel(stats.issued_this_month_count)} />
                <StatCard label="Pendiente de cobro" value={stats.pending_total_formatted} tone="warning" hint={invoicesLabel(stats.pending_count)} />
                <StatCard label="Vencido" value={stats.overdue_total_formatted} tone="danger" hint={invoicesLabel(stats.overdue_count)} />
            </section>

            <section aria-label="Últimos documentos" className="rounded-xl border bg-card">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <h2 className="font-semibold">Últimos documentos</h2>
                    <Link href="/invoices" className="flex items-center gap-1 text-sm text-primary hover:underline">
                        Ver facturas <ArrowRight className="size-4" aria-hidden />
                    </Link>
                </div>
                {recent.length === 0 ? (
                    <p className="p-8 text-center text-sm text-muted-foreground">Todavía no hay documentos. Crea tu primera factura.</p>
                ) : (
                    <ul>
                        {recent.map((row) => (
                            <li key={row.id} className="border-b last:border-b-0">
                                <Link href={`/${DOCUMENT_SEGMENTS[row.type]}/${row.id}`} className="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50">
                                    <span className="w-28 shrink-0 font-medium text-primary">{row.full_number ?? 'Borrador'}</span>
                                    <span className="min-w-0 flex-1 truncate">{row.customer_name ?? 'Sin cliente'}</span>
                                    <span className="hidden text-sm text-muted-foreground tabular-nums sm:inline">{formatDate(row.issue_date) || '—'}</span>
                                    <span className="w-28 text-right font-semibold tabular-nums">{row.total_formatted}</span>
                                    <span className="hidden w-24 text-right sm:block">
                                        <RowStatus
                                            status={row.status}
                                            statusLabel={row.status_label}
                                            paymentStatus={row.payment_status}
                                            paymentStatusLabel={row.payment_status_label}
                                        />
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
