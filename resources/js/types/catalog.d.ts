/** Clientes y productos: props de las páginas de customers/* y products/*. */

import type { DocumentRow } from '@/types/documents';

export interface Option {
    value: string;
    label: string;
}

export interface CatalogFilters {
    q: string | null;
    archived: boolean;
}

export interface CatalogCounts {
    active: number;
    archived: number;
}

export type CustomerKind = 'individual' | 'business';

export interface CustomerRow {
    id: string;
    kind: CustomerKind;
    kind_label: string;
    legal_name: string;
    trade_name: string | null;
    display_name: string;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    is_archived: boolean;
}

export interface Address {
    street: string;
    city: string;
    postal_code: string;
    province: string;
    country: string;
}

export interface ContactForm {
    name: string;
    email: string;
    phone: string;
    is_default: boolean;
}

/** Cliente tal como lo edita el formulario (CustomerForm en PHP). */
export interface CustomerForm {
    id: string;
    kind: CustomerKind;
    legal_name: string;
    trade_name: string;
    tax_id: string;
    tax_id_type: string | null;
    billing_address: Address;
    email: string;
    payment_terms_days: number;
    irpf_applies: boolean;
    surcharge_applies: boolean;
    default_vat_rate: string | null;
    notes: string;
    contacts: ContactForm[];
    is_archived: boolean;
}

export interface CustomerDetail extends CustomerForm {
    display_name: string;
    kind_label: string;
    tax_id_type_label: string | null;
    address_line: string | null;
}

export type ViesStatus = 'not_applicable' | 'validated' | 'pending';

export interface CustomerShowProps {
    customer: CustomerDetail;
    documents: DocumentRow[];
    vies: { validated_at: string | null; status: ViesStatus };
}

export interface ProductRow {
    id: string;
    sku: string | null;
    type: 'product' | 'service';
    type_label: string;
    name: string;
    description: string | null;
    /** Lo que se copia a la línea del documento: nombre y, debajo, la descripción. */
    line_description: string;
    /** Texto decimal con punto y tres decimales: "33.333". */
    unit_price: string;
    unit_price_formatted: string;
    unit: string;
    vat_rate: string;
    exemption_code: string | null;
    irpf_applicable: boolean;
    is_archived: boolean;
}

/** Aviso al guardar un cliente con un NIF que ya tiene otro. */
export interface DuplicateWarning {
    id: string;
    legal_name: string;
    tax_id: string;
}
