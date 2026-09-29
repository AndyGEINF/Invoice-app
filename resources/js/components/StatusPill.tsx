import { cn } from '@/lib/utils';

/** Tonos de estado: el color solo informa del estado, nunca decora. */
export type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

const PILL_CLASSES: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    info: 'bg-info-soft text-info-strong',
    success: 'bg-success-soft text-success-strong',
    warning: 'bg-warning-soft text-warning-strong',
    danger: 'bg-danger-soft text-danger-strong',
};

const DOT_CLASSES: Record<Tone, string> = {
    neutral: 'bg-muted-foreground/60',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
};

/** Pastilla de estado con punto de color. */
export function StatusPill({ tone, label, className }: { tone: Tone; label: string; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', PILL_CLASSES[tone], className)}>
            <span aria-hidden className={cn('size-1.5 rounded-full', DOT_CLASSES[tone])} />
            {label}
        </span>
    );
}
