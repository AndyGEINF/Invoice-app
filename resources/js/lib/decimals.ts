/**
 * Decimales en los campos de formulario. El servidor los envía y recibe con
 * punto y sin perder precisión ("2.0000", "33.333"); el usuario los ve y los
 * escribe con coma ("2", "33,333"). Solo se cambia el texto: nunca se pasan a
 * número, así no hay redondeos de coma flotante.
 */

/** "33.333" → "33,333"; "2.0000" → "2"; "10.50" → "10,5" */
export function decimalForInput(value: string): string {
    if (!value.includes('.')) {
        return value;
    }

    return value.replace(/\.?0+$/, '').replace('.', ',');
}

/** Precios unitarios: dos decimales como mucho (lo mismo que valida `App\Http\Requests\UnitPriceRule`). */
export const PRICE_DECIMALS = 2;

/**
 * Si lo escrito cabe en esos decimales ("33", "33,3", "33,33"; también a medio
 * escribir: "", "33,"). Los campos ignoran la tecla que lo haría pasar.
 */
export function fitsDecimals(value: string, decimals: number): boolean {
    return new RegExp(`^\\d*([.,]\\d{0,${decimals}})?$`).test(value.trim());
}

/** "33,333" → "33.333" */
export function decimalForServer(value: string): string {
    return value.trim().replace(',', '.');
}
