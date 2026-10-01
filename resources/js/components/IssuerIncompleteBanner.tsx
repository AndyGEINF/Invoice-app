import { Link, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

import { ISSUER_MISSING_LABELS } from '@/lib/issuer';

/**
 * Aviso permanente mientras faltan datos del emisor: sin ellos no se puede
 * emitir ninguna factura.
 */
export function IssuerIncompleteBanner() {
    const { issuer } = usePage().props;

    if (issuer.isComplete) {
        return null;
    }

    const missing = issuer.missing.map((field) => ISSUER_MISSING_LABELS[field]);

    return (
        <div role="alert" className="border-b border-warning/30 bg-warning-soft text-warning-strong">
            <div className="mx-auto flex max-w-[120rem] flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm md:px-8">
                <TriangleAlert aria-hidden className="size-4 shrink-0" />
                <p className="flex-1">
                    Para emitir facturas completa los datos de tu empresa. Falta {missing.join(', ')}.
                </p>
                <Link href="/settings/issuer" className="font-medium underline underline-offset-4">
                    Completar datos
                </Link>
            </div>
        </div>
    );
}
