import { usePage } from '@inertiajs/react';
import { CircleCheck, CircleX, TriangleAlert } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * Mensajes de la última acción (guardar, emitir, borrar…) y sus avisos.
 * Los envía el servidor con Inertia::flash() y duran una sola página.
 */
export function FlashMessages() {
    const { flash } = usePage();
    const warnings = flash.warnings ?? [];

    if (!flash.success && !flash.error && warnings.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 flex flex-col gap-2">
            {flash.success ? (
                <Alert className="border-success/30 bg-success-soft text-success-strong">
                    <CircleCheck aria-hidden />
                    <AlertTitle>{flash.success}</AlertTitle>
                </Alert>
            ) : null}

            {flash.error ? (
                <Alert variant="destructive">
                    <CircleX aria-hidden />
                    <AlertTitle>{flash.error}</AlertTitle>
                </Alert>
            ) : null}

            {warnings.map((warning) => (
                <Alert key={warning} className="border-warning/30 bg-warning-soft text-warning-strong">
                    <TriangleAlert aria-hidden />
                    <AlertTitle>Aviso</AlertTitle>
                    <AlertDescription className="text-current">{warning}</AlertDescription>
                </Alert>
            ))}
        </div>
    );
}
