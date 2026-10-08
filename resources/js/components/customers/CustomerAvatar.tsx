import { cn } from '@/lib/utils';

/** Círculo con la inicial del cliente. Sin colores aleatorios: neutro y legible. */
export function CustomerAvatar({ name, className }: { name: string; className?: string }) {
    return (
        <span
            aria-hidden
            className={cn('flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground', className)}
        >
            {name.trim().charAt(0).toUpperCase() || '?'}
        </span>
    );
}
