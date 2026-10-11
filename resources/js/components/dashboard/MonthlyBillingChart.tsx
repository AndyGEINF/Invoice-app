import { useState } from 'react';

import { monthAbbreviation, monthWithYear } from '@/lib/months';
import { cn } from '@/lib/utils';

export interface MonthlyBilling {
    /** `YYYY-MM` */
    month: string;
    total: number;
    total_formatted: string;
    invoices: number;
}

/** Altura del área de columnas, sin las etiquetas de los meses. */
const PLOT_HEIGHT_PX = 168;

/** Líneas de la rejilla, como fracción del máximo del eje. */
const GRID_STEPS = [0.5, 1];

const CENTS_PER_EURO = 100;

/**
 * Pasos "redondos" para el máximo del eje (× potencia de 10). Bastante finos
 * para que la columna más alta llegue cerca del techo y no quede media gráfica
 * vacía: 5.608 € → 6.000 €, no 10.000 €.
 */
const NICE_STEPS = [1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10];

const DECIMAL_BASE = 10;

const compactEuros = new Intl.NumberFormat('es-ES', { notation: 'compact', maximumFractionDigits: 1 });

/** El primer valor "redondo" (en euros enteros) que no queda por debajo del máximo. */
function niceCeilingEuros(maxCents: number): number {
    const euros = Math.ceil(maxCents / CENTS_PER_EURO);

    if (euros <= 0) {
        return 0;
    }

    const magnitude = DECIMAL_BASE ** Math.floor(Math.log10(euros));
    const step = NICE_STEPS.find((candidate) => candidate * magnitude >= euros) ?? DECIMAL_BASE;

    return step * magnitude;
}

/**
 * Facturación de los últimos doce meses. Una sola serie: los meses pasados en
 * gris y el actual en tinta (énfasis), sin color de estado. Cada columna se
 * puede enfocar con el teclado y muestra su importe; la tabla da los mismos
 * datos sin depender del gráfico. No se usa en móvil: doce columnas no caben
 * (el panel muestra allí un selector de mes).
 */
export function MonthlyBillingChart({ months }: { months: MonthlyBilling[] }) {
    const [active, setActive] = useState<number | null>(null);
    const [asTable, setAsTable] = useState(false);
    const maxCents = Math.max(0, ...months.map((month) => month.total));
    const ceilingEuros = niceCeilingEuros(maxCents);
    const ceilingCents = ceilingEuros * CENTS_PER_EURO;
    const lastIndex = months.length - 1;

    if (maxCents === 0) {
        return (
            <p className="flex h-40 items-center justify-center rounded-lg bg-muted/50 px-6 text-center text-sm text-muted-foreground">
                Cuando emitas facturas, aquí verás cuánto facturas cada mes.
            </p>
        );
    }

    return (
        <figure className="flex flex-col gap-3">
            <figcaption className="flex items-baseline justify-between gap-4 text-sm">
                <span className="text-muted-foreground">Facturación de los últimos 12 meses</span>
                <button type="button" onClick={() => setAsTable(!asTable)} className="text-primary hover:underline">
                    {asTable ? 'Ver gráfico' : 'Ver tabla'}
                </button>
            </figcaption>

            {asTable ? (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-muted-foreground">
                            <th className="py-1.5 font-normal">Mes</th>
                            <th className="py-1.5 text-right font-normal">Facturas</th>
                            <th className="py-1.5 text-right font-normal">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        {[...months].reverse().map((month) => (
                            <tr key={month.month} className="border-t">
                                <td className="py-1.5 first-letter:uppercase">{monthWithYear(month.month)}</td>
                                <td className="py-1.5 text-right tabular-nums">{month.invoices}</td>
                                <td className="py-1.5 text-right tabular-nums">{month.total_formatted}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                // pt-3: la etiqueta del techo del eje sobresale media línea por arriba.
                <div className="flex gap-3 pt-3">
                    {/* Eje: solo dos referencias redondas; el detalle está en cada columna y en la tabla. */}
                    <div className="relative w-12 shrink-0 text-right text-xs text-muted-foreground tabular-nums" style={{ height: PLOT_HEIGHT_PX }}>
                        {GRID_STEPS.map((step) => (
                            <span key={step} className="absolute right-0 -translate-y-1/2" style={{ bottom: `${step * 100}%` }}>
                                {compactEuros.format(ceilingEuros * step)} €
                            </span>
                        ))}
                        <span className="absolute right-0 bottom-0 translate-y-1/2">0</span>
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="relative border-b border-border" style={{ height: PLOT_HEIGHT_PX }}>
                            {GRID_STEPS.map((step) => (
                                <div key={step} aria-hidden className="absolute inset-x-0 border-t border-border" style={{ bottom: `${step * 100}%` }} />
                            ))}

                            <ol className="absolute inset-0 grid" style={{ gridTemplateColumns: `repeat(${months.length}, minmax(0, 1fr))` }}>
                                {months.map((month, index) => {
                                    const isCurrent = index === lastIndex;
                                    const height = ceilingCents > 0 ? (month.total / ceilingCents) * 100 : 0;
                                    const label = `${monthWithYear(month.month)}: ${month.total_formatted}, ${month.invoices === 1 ? '1 factura' : `${month.invoices} facturas`}`;

                                    return (
                                        <li key={month.month} className="relative flex h-full items-end justify-center">
                                            <button
                                                type="button"
                                                aria-label={label}
                                                onMouseEnter={() => setActive(index)}
                                                onMouseLeave={() => setActive(null)}
                                                onFocus={() => setActive(index)}
                                                onBlur={() => setActive(null)}
                                                className="group flex h-full w-full items-end justify-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                            >
                                                <span
                                                    aria-hidden
                                                    className={cn(
                                                        'block w-full max-w-6 rounded-t-[4px] transition-opacity',
                                                        isCurrent ? 'bg-bar-current' : 'bg-bar-past',
                                                        active !== null && active !== index && 'opacity-60',
                                                    )}
                                                    style={{ height: `${height}%` }}
                                                />
                                            </button>

                                            {active === index ? (
                                                <span
                                                    role="status"
                                                    className="pointer-events-none absolute z-10 mb-2 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-sm"
                                                    style={{ bottom: `${Math.max(height, 0)}%` }}
                                                >
                                                    <span className="block text-muted-foreground first-letter:uppercase">{monthWithYear(month.month)}</span>
                                                    <span className="block font-semibold tabular-nums">{month.total_formatted}</span>
                                                </span>
                                            ) : null}
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>

                        <ol aria-hidden className="mt-2 grid text-center text-xs text-muted-foreground" style={{ gridTemplateColumns: `repeat(${months.length}, minmax(0, 1fr))` }}>
                            {months.map((month, index) => (
                                <li key={month.month} className={cn(index === lastIndex && 'font-medium text-foreground')}>
                                    {monthAbbreviation(month.month)}
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
            )}
        </figure>
    );
}
