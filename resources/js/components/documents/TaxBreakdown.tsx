import { formatPercentage } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { TaxBreakdown as Breakdown } from '@/types/documents';

const TOTAL_ROWS: { key: 'taxable_base' | 'vat_total' | 'surcharge_total' | 'irpf_total'; label: string; negative?: boolean; optional?: boolean }[] = [
    { key: 'taxable_base', label: 'Base imponible' },
    { key: 'vat_total', label: 'IVA' },
    { key: 'surcharge_total', label: 'Recargo de equivalencia', optional: true },
    { key: 'irpf_total', label: 'Retención IRPF', negative: true, optional: true },
];

/**
 * Desglose y totales tal como los calculó el servidor en el último guardado.
 * El frontend nunca calcula: con cambios pendientes se avisa y se atenúan.
 */
export function TaxBreakdown({ breakdown, stale }: { breakdown: Breakdown | null; stale: boolean }) {
    if (!breakdown) {
        return <p className="text-sm text-muted-foreground">El desglose y el total se calculan al guardar.</p>;
    }

    return (
        <div>
            <dl className={cn('text-sm transition-opacity', stale && 'opacity-50')}>
                {breakdown.taxes.length > 0 ? (
                    <div className="mb-3 flex flex-col gap-1 text-xs text-muted-foreground">
                        {breakdown.taxes.map((tax) => (
                            <div key={`${tax.tax_type}-${tax.rate}-${tax.exemption_code ?? ''}`} className="flex justify-between gap-4">
                                <dt>
                                    {tax.tax_type_label} {tax.exemption_code ?? formatPercentage(tax.rate)}{' '}
                                    <span className="tabular-nums">sobre {tax.base_formatted}</span>
                                </dt>
                                <dd className="tabular-nums">
                                    {tax.tax_type === 'IRPF' ? '-' : ''}
                                    {tax.amount_formatted}
                                </dd>
                            </div>
                        ))}
                    </div>
                ) : null}

                {TOTAL_ROWS.filter((row) => !row.optional || breakdown[row.key] !== 0).map((row) => (
                    <div key={row.key} className="flex justify-between gap-4 py-1">
                        <dt className="text-muted-foreground">{row.label}</dt>
                        <dd className="tabular-nums">
                            {row.negative ? '-' : ''}
                            {breakdown.totals_formatted[row.key]}
                        </dd>
                    </div>
                ))}

                <div className="mt-2 flex justify-between gap-4 border-t pt-3 text-base font-semibold">
                    <dt>Total</dt>
                    <dd className="tabular-nums">{breakdown.totals_formatted.total}</dd>
                </div>
            </dl>
            {stale ? <p className="mt-2 text-xs text-warning-strong">Hay cambios sin guardar: guarda para recalcular el total.</p> : null}
        </div>
    );
}
