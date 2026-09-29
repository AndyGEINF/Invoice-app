/**
 * Props de las páginas de documentos. Reflejan los resources de PHP
 * (app/Http/Resources) y contracts/web-routes.md: importes en céntimos más su
 * texto formateado, decimales como texto y fechas "AAAA-MM-DD".
 */

export type DocumentTypeValue = 'quote' | 'invoice' | 'credit_note';

export type FiscalStatus = 'draft' | 'issued' | 'sent' | 'rectified';

export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'converted';

export type DocumentStatus = FiscalStatus | QuoteStatus;

export type PaymentStatus = 'unpaid' | 'paid' | 'overdue';

export type TaxTypeValue = 'IVA' | 'RE' | 'IRPF';

/** Tipo de la página: título y segmento de URL (/invoices, /credit-notes…). */
export interface DocumentTypeProps {
    value: DocumentTypeValue;
    label: string;
    segment: string;
}

/** Paginador de Laravel (LengthAwarePaginator::toArray). */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface SeriesOption {
    id: string;
    code: string;
    prefix: string;
    is_default: boolean;
}

export interface DocumentRow {
    id: string;
    type: DocumentTypeValue;
    status: DocumentStatus;
    status_label: string;
    payment_status: PaymentStatus | null;
    payment_status_label: string | null;
    full_number: string | null;
    issue_date: string | null;
    due_date: string | null;
    customer_name: string | null;
    paid_at: string | null;
    total: number;
    total_formatted: string;
}

export interface DocumentFilters {
    q: string | null;
    status: DocumentStatus | null;
    payment_status: PaymentStatus | null;
    customer_id: string | null;
    from: string | null;
    to: string | null;
    series_id: string | null;
}

export interface DocumentTotals {
    count: number;
    sum_total: number;
    sum_total_formatted: string;
    sum_pending: number;
    sum_pending_formatted: string;
    sum_overdue: number;
    sum_overdue_formatted: string;
}

/** Tarjeta de aviso del listado: cuántos, cuánto suman y los más urgentes. */
export interface AlertGroup {
    count: number;
    sum_formatted: string;
    items: DocumentRow[];
}

export interface DocumentAlerts {
    overdue: AlertGroup;
    drafts: AlertGroup;
}

export interface TaxRow {
    tax_type: TaxTypeValue;
    tax_type_label: string;
    rate: string;
    exemption_code: string | null;
    base: number;
    base_formatted: string;
    amount: number;
    amount_formatted: string;
}

type TotalKey = 'taxable_base' | 'vat_total' | 'surcharge_total' | 'irpf_total' | 'total';

/** Desglose calculado por el servidor: el frontend nunca calcula. */
export type TaxBreakdown = {
    taxes: TaxRow[];
    totals_formatted: Record<TotalKey, string>;
} & Record<TotalKey, number>;

export interface DraftLine {
    id?: string | null;
    position: number;
    product_id?: string | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount_percent: string;
    vat_rate: string;
    surcharge_rate: string;
    irpf_applies: boolean;
    exemption_code?: string | null;
    line_base?: number;
    line_base_formatted?: string;
}

/** Payload del formulario de borrador (DraftForm). */
export interface DraftForm {
    id: string | null;
    customer_id: string | null;
    series_id: string | null;
    issue_date: string | null;
    valid_until: string | null;
    due_date: string | null;
    operation_date: string | null;
    currency: string;
    global_discount_percent: string;
    irpf_rate: string;
    notes: string | null;
    internal_notes: string | null;
    lines: DraftLine[];
    breakdown: TaxBreakdown | null;
}

export interface DraftDefaults {
    vat_rate: string;
    irpf_rate: string;
    currency: string;
}

export interface LineView {
    id: string;
    position: number;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount_percent: string;
    vat_rate: string;
    surcharge_rate: string;
    irpf_applies: boolean;
    exemption_code: string | null;
    line_base: number;
    line_base_formatted: string;
}

export interface PartySnapshot {
    legal_name: string;
    tax_id: string | null;
    address: { street: string; city: string; postal_code: string; province: string; country: string } | null;
    [key: string]: unknown;
}

export type DocumentDetail = {
    id: string;
    type: DocumentTypeValue;
    type_label: string;
    status: DocumentStatus;
    status_label: string;
    payment_status: PaymentStatus | null;
    payment_status_label: string | null;
    is_expired: boolean | null;
    paid_at: string | null;
    paid_note: string | null;
    full_number: string | null;
    series_code: string | null;
    issue_date: string | null;
    operation_date: string | null;
    due_date: string | null;
    valid_until: string | null;
    invoice_type: 'F1' | 'F2' | 'R1' | 'R4' | 'R5' | null;
    invoice_type_label: string | null;
    customer: { id: string; legal_name: string; tax_id: string | null } | null;
    issuer_snapshot: PartySnapshot | null;
    customer_snapshot: PartySnapshot | null;
    lines: LineView[];
    global_discount_percent: string;
    irpf_rate: string;
    currency: string;
    notes: string | null;
    internal_notes: string | null;
    issued_at: string | null;
    pdf_available: boolean;
} & TaxBreakdown;

export interface DocumentEventView {
    id: string;
    event: string;
    label: string;
    payload: Record<string, unknown> | null;
    created_at: string;
}

export interface DocumentSendView {
    id: string;
    to: string[];
    subject: string;
    status: 'queued' | 'sent' | 'failed';
    status_label: string;
    error: string | null;
    queued_at: string;
    sent_at: string | null;
}

export interface RelatedLink {
    id: string;
    type: DocumentTypeValue;
    segment: string;
    full_number: string | null;
}

export interface DocumentRelations {
    converted_from: RelatedLink | null;
    converted_to: RelatedLink | null;
    rectifies: RelatedLink | null;
    rectified_by: RelatedLink[];
}

export interface DocumentAbilities {
    edit: boolean;
    issue: boolean;
    delete: boolean;
    send: boolean;
    rectify: boolean;
    convert: boolean;
    mark_paid: boolean;
    unmark_paid: boolean;
}
