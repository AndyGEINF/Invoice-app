import { ExternalLink, Package } from 'lucide-react';

import { SearchPicker } from '@/components/SearchPicker';
import { Button } from '@/components/ui/button';
import { decimalForInput } from '@/lib/decimals';
import type { ProductRow } from '@/types/catalog';

/**
 * "Añadir del catálogo": busca productos y servicios activos. Al elegir uno, el
 * padre crea una línea con una copia de sus datos.
 */
export function ProductPicker({ onSelect }: { onSelect: (product: ProductRow) => void }) {
    return (
        <SearchPicker<ProductRow>
            url="/products/search"
            label="Buscar en el catálogo"
            placeholder="Nombre o referencia"
            emptyText="Nada del catálogo coincide."
            getKey={(product) => product.id}
            onSelect={onSelect}
            renderItem={(product) => (
                <span className="flex items-center justify-between gap-3">
                    <span className="min-w-0">
                        <span className="block truncate font-medium">{product.name}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {[product.sku, product.exemption_code ? `Exento (${product.exemption_code})` : `IVA ${decimalForInput(product.vat_rate)} %`]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </span>
                    <span className="shrink-0 text-right tabular-nums">
                        {product.unit_price_formatted}
                        <span className="text-xs text-muted-foreground"> / {product.unit}</span>
                    </span>
                </span>
            )}
            footer={
                <a
                    href="/products/create"
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-primary hover:bg-accent"
                >
                    <ExternalLink aria-hidden className="size-4" />
                    Crear un producto nuevo
                </a>
            }
            panelClassName="w-96 max-w-[calc(100vw-2rem)]"
            trigger={({ open, toggle, listId }) => (
                <Button
                    type="button"
                    variant="ghost"
                    className="text-primary"
                    onClick={toggle}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-controls={open ? listId : undefined}
                >
                    <Package aria-hidden />
                    Añadir del catálogo
                </Button>
            )}
        />
    );
}
