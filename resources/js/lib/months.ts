/** Meses como `YYYY-MM` (lo que envía el servidor) → fechas y nombres en español. */

export function monthDate(month: string): Date {
    const [year, monthNumber] = month.split('-').map(Number);

    return new Date(Date.UTC(year ?? 0, (monthNumber ?? 1) - 1, 1));
}

const monthOnly = new Intl.DateTimeFormat('es-ES', { month: 'long', timeZone: 'UTC' });
const monthAndYear = new Intl.DateTimeFormat('es-ES', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const monthShort = new Intl.DateTimeFormat('es-ES', { month: 'short', timeZone: 'UTC' });

/** "2026-10" → "octubre" */
export function monthName(month: string): string {
    return monthOnly.format(monthDate(month));
}

/** "2026-10" → "octubre de 2026" */
export function monthWithYear(month: string): string {
    return monthAndYear.format(monthDate(month));
}

/** "2026-10" → "oct" */
export function monthAbbreviation(month: string): string {
    return monthShort.format(monthDate(month)).replace('.', '');
}
