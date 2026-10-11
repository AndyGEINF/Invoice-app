import { cn } from '@/lib/utils';

/** Tonos de estado: el color solo informa del estado, nunca decora. */
export type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

/** Texto en el color del estado, marcado con su tono suave. */
const TONE_CLASSES: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    info: 'bg-info-soft text-info-strong',
    success: 'bg-success-soft text-success-strong',
    warning: 'bg-warning-soft text-warning-strong',
    danger: 'bg-danger-soft text-danger-strong',
};

/** Estado: solo la palabra en el color del estado, resaltada como con un marcador. Sin punto. */
export function StatusPill({ tone, label, className }: { tone: Tone; label: string; className?: string }) {
    return <span className={cn('rounded-[2px] px-1 text-sm font-medium whitespace-nowrap', TONE_CLASSES[tone], className)}>{label}</span>;
}
