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
                <Alert className="border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100">
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
                <Alert key={warning} className="border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                    <TriangleAlert aria-hidden />
                    <AlertTitle>Aviso</AlertTitle>
                    <AlertDescription className="text-current">{warning}</AlertDescription>
                </Alert>
            ))}
        </div>
    );
}
