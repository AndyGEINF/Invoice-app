/**
 * Fechas en formato español. El servidor envía las fechas como "AAAA-MM-DD";
 * se interpretan como fecha local para que no cambien de día por la zona horaria.
 */

const LOCALE = 'es-ES';

function parseDate(isoDate: string): Date {
    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);

    return new Date(year ?? 1970, (month ?? 1) - 1, day ?? 1);
}

/** "2026-09-23" → "23/09/2026" */
export function formatDate(isoDate: string | null | undefined): string {
    if (!isoDate) {
        return '';
    }

    return new Intl.DateTimeFormat(LOCALE, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parseDate(isoDate));
}

/** "2026-09-23" → "23 sept 2026" */
export function formatDateLong(isoDate: string | null | undefined): string {
    if (!isoDate) {
        return '';
    }

    return new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'short', year: 'numeric' }).format(parseDate(isoDate));
}

/** Instante ISO 8601 → "29 sept 2026, 10:30" en la hora local de quien mira. */
export function formatDateTime(isoDateTime: string | null | undefined): string {
    if (!isoDateTime) {
        return '';
    }

    return new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(
        new Date(isoDateTime),
    );
}

/** Fecha de hoy en formato "AAAA-MM-DD", para valores por defecto de formularios. */
export function todayIso(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}
