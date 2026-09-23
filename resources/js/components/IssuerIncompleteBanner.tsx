import { Link, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

import type { IssuerMissingField } from '@/types';

const MISSING_LABELS: Record<IssuerMissingField, string> = {
    name: 'tu nombre',
    logo: 'el logotipo',
    tax_id: 'un NIF válido',
    address: 'la dirección completa',
};

/**
 * Aviso permanente mientras faltan datos del emisor: sin ellos no se puede
 * emitir ninguna factura.
 */
export function IssuerIncompleteBanner() {
    const { issuer } = usePage().props;

    if (issuer.isComplete) {
        return null;
    }

    const missing = issuer.missing.map((field) => MISSING_LABELS[field]);

    return (
        <div role="alert" className="border-b border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-sm">
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
