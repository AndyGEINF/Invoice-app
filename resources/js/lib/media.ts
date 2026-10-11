import { useSyncExternalStore } from 'react';

/** Por debajo del punto `sm` de Tailwind (40rem): pantallas de móvil. */
export const NARROW_QUERY = '(width < 40rem)';

/** Si la ventana cumple la media query, y se actualiza al cambiar de tamaño. */
export function useMediaQuery(query: string): boolean {
    return useSyncExternalStore(
        (onChange) => {
            const media = window.matchMedia(query);
            media.addEventListener('change', onChange);

            return () => media.removeEventListener('change', onChange);
        },
        () => window.matchMedia(query).matches,
        () => false,
    );
}
