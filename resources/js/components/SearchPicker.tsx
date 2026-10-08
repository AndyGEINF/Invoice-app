import { Loader2, Search } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useEffect, useId, useRef, useState } from 'react';

import { SEARCH_DEBOUNCE_MS } from '@/lib/use-debounced-search';
import { cn } from '@/lib/utils';

/** Opción fija al principio de la lista (p. ej. "Sin cliente"). */
interface FixedOption {
    label: ReactNode;
    onSelect: () => void;
}

/**
 * Buscador desplegable contra un endpoint JSON (`?q=`) que devuelve una lista.
 *
 * Abre con el disparador, busca mientras se escribe y se maneja con el teclado
 * (↑ ↓ Intro Esc). No guarda estado propio del valor elegido: avisa con
 * `onSelect` y el padre decide.
 */
export function SearchPicker<T>({
    url,
    label,
    placeholder,
    emptyText,
    getKey,
    renderItem,
    onSelect,
    leading,
    footer,
    trigger,
    className,
    panelClassName,
}: {
    url: string;
    /** Nombre accesible del campo de búsqueda. */
    label: string;
    placeholder: string;
    emptyText: string;
    getKey: (item: T) => string;
    renderItem: (item: T) => ReactNode;
    onSelect: (item: T) => void;
    leading?: FixedOption;
    footer?: ReactNode;
    trigger: (props: { open: boolean; toggle: () => void; listId: string }) => ReactNode;
    className?: string;
    panelClassName?: string;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<T[]>([]);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const root = useRef<HTMLDivElement>(null);
    const listId = useId();

    const hasLeading = leading !== undefined;
    const options = hasLeading ? [null, ...items] : items;

    // Busca al abrir y cada vez que cambia el texto, con espera y cancelando la anterior.
    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(
            () => {
                setLoading(true);
                fetch(`${url}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                    .then((response) => (response.ok ? (response.json() as Promise<T[]>) : []))
                    .then((result) => {
                        setItems(result);
                        // Con texto escrito, Intro elige el primer resultado y no la opción fija.
                        setActive(hasLeading && query !== '' && result.length > 0 ? 1 : 0);
                    })
                    .catch(() => undefined)
                    .finally(() => setLoading(false));
            },
            query === '' ? 0 : SEARCH_DEBOUNCE_MS,
        );

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, query, url, hasLeading]);

    // Cierra al pulsar fuera.
    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: PointerEvent) {
            if (root.current && !root.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    function close() {
        setOpen(false);
        setQuery('');
    }

    function choose(index: number) {
        const option = options[index];

        if (option === undefined) {
            return;
        }

        if (option === null) {
            leading?.onSelect();
        } else {
            onSelect(option);
        }

        close();
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => Math.min(index + 1, options.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            choose(active);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    }

    return (
        <div ref={root} className={cn('relative', className)}>
            {trigger({ open, toggle: () => (open ? close() : setOpen(true)), listId })}

            {open ? (
                <div className={cn('absolute z-50 mt-1 w-full min-w-72 overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg', panelClassName)}>
                    <div className="flex items-center gap-2 border-b px-3">
                        {loading ? (
                            <Loader2 aria-hidden className="size-4 shrink-0 animate-spin text-muted-foreground" />
                        ) : (
                            <Search aria-hidden className="size-4 shrink-0 text-muted-foreground" />
                        )}
                        <input
                            autoFocus
                            role="combobox"
                            aria-expanded
                            aria-controls={listId}
                            aria-activedescendant={options.length > 0 ? `${listId}-${active}` : undefined}
                            aria-label={label}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder={placeholder}
                            className="h-10 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </div>

                    <ul id={listId} role="listbox" aria-label={label} className="max-h-72 overflow-y-auto p-1">
                        {options.map((option, index) => (
                            <li
                                key={option === null ? 'leading' : getKey(option)}
                                id={`${listId}-${index}`}
                                role="option"
                                aria-selected={index === active}
                                onPointerEnter={() => setActive(index)}
                                onClick={() => choose(index)}
                                className={cn('cursor-pointer rounded-md px-2 py-1.5 text-sm', index === active && 'bg-accent text-accent-foreground')}
                            >
                                {option === null ? leading?.label : renderItem(option)}
                            </li>
                        ))}
                        {!loading && items.length === 0 ? <li className="px-2 py-3 text-center text-sm text-muted-foreground">{emptyText}</li> : null}
                    </ul>

                    {footer ? <div className="border-t p-1">{footer}</div> : null}
                </div>
            ) : null}
        </div>
    );
}
