/**
 * Formateo de importes para mostrar.
 *
 * El frontend nunca calcula impuestos ni totales: el servidor devuelve los
 * importes en céntimos ya calculados y aquí solo se les da formato. Mantener el
 * cálculo en un único sitio evita que pantalla y PDF muestren cifras distintas.
 */

const LOCALE = 'es-ES';

const CENTS_PER_UNIT = 100;

const formatters = new Map<string, Intl.NumberFormat>();

function currencyFormatter(currency: string): Intl.NumberFormat {
    let formatter = formatters.get(currency);

    if (!formatter) {
        formatter = new Intl.NumberFormat(LOCALE, { style: 'currency', currency });
        formatters.set(currency, formatter);
    }

    return formatter;
}

/** 19250 → "192,50 €" */
export function formatCents(cents: number, currency = 'EUR'): string {
    return currencyFormatter(currency).format(cents / CENTS_PER_UNIT);
}

/** "21.00" → "21 %" y "5.20" → "5,2 %" */
export function formatPercentage(rate: string): string {
    const value = Number(rate);

    return `${new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2 }).format(value)} %`;
}
