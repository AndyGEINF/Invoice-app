import { useCallback, useEffect, useState } from 'react';

/**
 * Tema claro/oscuro. Por defecto sigue al sistema; si el usuario elige uno, se
 * recuerda en este navegador. El script en línea de resources/views/app.blade.php
 * aplica la misma lógica antes de pintar para que no haya parpadeo: si cambias
 * la clave o las reglas aquí, cámbialas también allí.
 */

export type ThemePreference = 'light' | 'dark' | 'system';

export const THEME_STORAGE_KEY = 'invoice.theme';

const DARK_QUERY = '(prefers-color-scheme: dark)';

const PREFERENCES: ThemePreference[] = ['light', 'dark', 'system'];

export function storedTheme(): ThemePreference {
    try {
        const value = window.localStorage.getItem(THEME_STORAGE_KEY);

        return PREFERENCES.includes(value as ThemePreference) ? (value as ThemePreference) : 'system';
    } catch {
        return 'system';
    }
}

export function applyTheme(preference: ThemePreference): void {
    const dark = preference === 'dark' || (preference === 'system' && window.matchMedia(DARK_QUERY).matches);
    const root = document.documentElement;

    root.classList.toggle('dark', dark);
    root.style.colorScheme = dark ? 'dark' : 'light';
}

/** Preferencia actual y cómo cambiarla. Con "sistema" sigue los cambios del sistema en vivo. */
export function useTheme(): [ThemePreference, (preference: ThemePreference) => void] {
    const [preference, setPreference] = useState<ThemePreference>(storedTheme);

    useEffect(() => {
        applyTheme(preference);

        if (preference !== 'system') {
            return;
        }

        const media = window.matchMedia(DARK_QUERY);
        const onChange = () => applyTheme('system');
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, [preference]);

    const update = useCallback((next: ThemePreference) => {
        try {
            if (next === 'system') {
                window.localStorage.removeItem(THEME_STORAGE_KEY);
            } else {
                window.localStorage.setItem(THEME_STORAGE_KEY, next);
            }
        } catch {
            // Sin almacenamiento (modo privado): el tema dura lo que la pestaña.
        }

        setPreference(next);
    }, []);

    return [preference, update];
}
