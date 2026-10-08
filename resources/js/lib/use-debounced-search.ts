import { useEffect, useRef, useState } from 'react';

/** Espera tras la última tecla antes de buscar, para no lanzar una petición por letra. */
export const SEARCH_DEBOUNCE_MS = 300;

/**
 * Texto de un buscador que avisa con `onSearch` cuando se deja de escribir.
 * No avisa al montar: la página ya llega filtrada con el valor inicial.
 */
export function useDebouncedSearch(initial: string, onSearch: (value: string) => void): [string, (value: string) => void] {
    const [value, setValue] = useState(initial);
    const firstRender = useRef(true);
    const callback = useRef(onSearch);

    useEffect(() => {
        callback.current = onSearch;
    });

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => callback.current(value), SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [value]);

    return [value, setValue];
}
