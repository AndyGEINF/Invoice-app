import { ChevronsUpDown, ExternalLink, UserX } from 'lucide-react';

import { SearchPicker } from '@/components/SearchPicker';
import { cn } from '@/lib/utils';
import type { CustomerOption } from '@/types/documents';

/**
 * Selector del cliente del documento: busca por nombre, NIF o email entre los
 * clientes activos. "Sin cliente" deja la factura como simplificada.
 */
export function CustomerPicker({
    value,
    onChange,
    invalid,
}: {
    value: CustomerOption | null;
    onChange: (customer: CustomerOption | null) => void;
    invalid?: boolean;
}) {
    return (
        <SearchPicker<CustomerOption>
            url="/customers/search"
            label="Buscar cliente"
            placeholder="Nombre, NIF o email"
            emptyText="Ningún cliente coincide."
            getKey={(customer) => customer.id}
            onSelect={onChange}
            leading={{
                label: (
                    <span className="flex items-center gap-2 text-muted-foreground">
                        <UserX aria-hidden className="size-4" />
                        Sin cliente (factura simplificada)
                    </span>
                ),
                onSelect: () => onChange(null),
            }}
            renderItem={(customer) => (
                <span className="block min-w-0">
                    <span className="block truncate font-medium">{customer.trade_name || customer.legal_name}</span>
                    <span className="block truncate text-xs text-muted-foreground">
                        {[customer.trade_name ? customer.legal_name : null, customer.tax_id ?? 'Sin NIF'].filter(Boolean).join(' · ')}
                    </span>
                </span>
            )}
            footer={
                // En otra pestaña: así no se pierde el borrador que se está escribiendo.
                <a
                    href="/customers/create"
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-primary hover:bg-accent"
                >
                    <ExternalLink aria-hidden className="size-4" />
                    Crear un cliente nuevo
                </a>
            }
            trigger={({ open, toggle, listId }) => (
                <button
                    type="button"
                    onClick={toggle}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-controls={open ? listId : undefined}
                    aria-label={value ? `Cliente: ${value.legal_name}. Cambiar` : 'Elegir cliente'}
                    aria-invalid={invalid}
                    className={cn(
                        'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-xs transition-colors hover:bg-muted/50',
                        'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                        invalid && 'border-destructive',
                    )}
                >
                    <span className={cn('truncate', !value && 'text-muted-foreground')}>
                        {value ? value.trade_name || value.legal_name : 'Sin cliente (factura simplificada)'}
                    </span>
                    <ChevronsUpDown aria-hidden className="size-4 shrink-0 text-muted-foreground" />
                </button>
            )}
        />
    );
}
