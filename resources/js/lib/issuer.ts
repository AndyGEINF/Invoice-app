import type { IssuerMissingField } from '@/types';

/** Cómo se nombra cada dato del emisor que falta (claves de Issuer::missing()). */
export const ISSUER_MISSING_LABELS: Record<IssuerMissingField, string> = {
    name: 'tu nombre',
    logo: 'el logotipo',
    tax_id: 'un NIF válido',
    address: 'la dirección completa',
};
