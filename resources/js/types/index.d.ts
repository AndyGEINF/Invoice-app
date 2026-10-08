import '@inertiajs/core';

/** Qué falta para poder emitir. Coincide con las claves de Issuer::missing() en PHP. */
export type IssuerMissingField = 'name' | 'logo' | 'tax_id' | 'address';

export interface IssuerSummary {
    name: string | null;
    companyName: string | null;
    legalName: string | null;
    logoUrl: string | null;
    isComplete: boolean;
    missing: IssuerMissingField[];
}

export interface InvoiceConfig {
    vatRates: string[];
    surchargeRates: string[];
    surchargeByVatRate: Record<string, string>;
    irpfRates: string[];
    exemptionCodes: Record<string, string>;
    defaultCurrency: string;
}

export interface SharedProps {
    appName: string;
    issuer: IssuerSummary;
    invoiceConfig: InvoiceConfig;
    errors: Record<string, string>;
}

export interface FlashData {
    success?: string;
    error?: string;
    warnings?: string[];
    /** Al guardar un cliente con un NIF que ya tiene otro (ver types/catalog.d.ts). */
    duplicate_warning?: { id: string; legal_name: string; tax_id: string };
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
        flashDataType: FlashData;
    }
}
