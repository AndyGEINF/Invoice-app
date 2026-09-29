import type { ReactNode } from 'react';

/** Cabecera de cada página: título, una línea de contexto (p. ej. "6 facturas") y acciones. */
export function PageHeader({ title, description, actions }: { title: string; description?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div className="min-w-0">
                <h1 className="truncate text-xl font-semibold tracking-tight">{title}</h1>
                {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
            </div>
            {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
        </div>
    );
}
