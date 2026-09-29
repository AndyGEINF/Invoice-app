import type { DocumentTypeProps, DocumentTypeValue } from '@/types/documents';

/** Título de cada listado. */
export const DOCUMENT_LIST_TITLES: Record<DocumentTypeValue, string> = {
    quote: 'Presupuestos',
    invoice: 'Facturas',
    credit_note: 'Rectificativas',
};

/**
 * Texto del botón de alta. Las rectificativas no tienen alta propia: nacen de
 * la factura que corrigen.
 */
export const NEW_DOCUMENT_LABELS: Partial<Record<DocumentTypeValue, string>> = {
    quote: 'Nuevo presupuesto',
    invoice: 'Nueva factura',
};

/** URL de un documento: /invoices, /invoices/{id}, /invoices/{id}/edit… */
export function documentUrl(type: DocumentTypeProps, id?: string, action?: 'edit' | 'preview' | 'issue'): string {
    const base = `/${type.segment}`;

    if (!id) {
        return base;
    }

    return action ? `${base}/${id}/${action}` : `${base}/${id}`;
}
