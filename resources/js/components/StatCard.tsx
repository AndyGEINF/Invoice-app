import type { ReactNode } from 'react';

import type { Tone } from '@/components/StatusPill';
import { cn } from '@/lib/utils';

const VALUE_CLASSES: Record<Tone, string> = {
    neutral: 'text-foreground',
    info: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
};

/**
 * Cifra destacada (total emitido, pendiente, vencido…). El color del valor dice
 * de qué se trata; la tarjeta siempre es blanca.
 */
export function StatCard({
    label,
    value,
    tone = 'neutral',
    hint,
    children,
}: {
    label: string;
    value: string;
    tone?: Tone;
    hint?: string;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-card px-4 py-3">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className={cn('mt-0.5 text-lg font-semibold tabular-nums', VALUE_CLASSES[tone])}>{value}</p>
            {children}
            {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
        </div>
    );
}
