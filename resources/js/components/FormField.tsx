import type { ReactNode } from 'react';

import { FieldError } from '@/components/documents/LineEditor';
import { Label } from '@/components/ui/label';

/** Campo de formulario: etiqueta (con * si es obligatorio), control, ayuda y error. */
export function Field({
    id,
    label,
    required,
    hint,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={className}>
            <Label htmlFor={id} className="mb-1.5">
                {label}
                {required ? <span className="text-destructive"> *</span> : null}
            </Label>
            {children}
            {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
            <FieldError message={error} />
        </div>
    );
}

/** Bloque de un formulario largo: título, una línea de contexto y sus campos. */
export function FormSection({ title, description, actions, children }: { title: string; description?: string; actions?: ReactNode; children: ReactNode }) {
    return (
        <section className="mb-6 rounded-xl border bg-card p-5 md:p-6">
            <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
                </div>
                {actions}
            </div>
            {children}
        </section>
    );
}
