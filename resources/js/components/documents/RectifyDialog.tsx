import { useForm } from '@inertiajs/react';
import { FilePen } from 'lucide-react';

import { FieldError } from '@/components/documents/LineEditor';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { documentUrl } from '@/lib/documents';
import { cn } from '@/lib/utils';
import type { DocumentTypeProps, RectificationType } from '@/types/documents';

/** Lo mismo que admite el servidor (`RectifyInvoiceRequest::MAX_REASON_LENGTH`). */
const MAX_REASON_LENGTH = 500;

const OPTIONS: { value: RectificationType; title: string; description: string }[] = [
    {
        value: 'S',
        title: 'Por sustitución',
        description: 'La rectificativa sustituye a la factura entera. Empieza con las mismas líneas: cambia lo que estaba mal.',
    },
    {
        value: 'I',
        title: 'Por diferencias',
        description: 'Solo recoge la diferencia. Empieza anulando todas las líneas (en negativo): deja solo lo que hay que devolver.',
    },
];

/**
 * Rectificar una factura emitida: cómo y por qué. Crea la rectificativa en
 * borrador y la abre para revisarla antes de emitirla.
 */
export function RectifyDialog({
    open,
    onOpenChange,
    type,
    documentId,
    fullNumber,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    type: DocumentTypeProps;
    documentId: string;
    fullNumber: string | null;
}) {
    const form = useForm<{ rectification_type: RectificationType; reason: string }>({ rectification_type: 'S', reason: '' });
    const errors = form.errors as Record<string, string>;

    function submit() {
        form.post(documentUrl(type, documentId, 'rectify'), {
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rectificar la factura {fullNumber}</DialogTitle>
                    <DialogDescription>
                        Una factura emitida no se modifica: se corrige con una rectificativa. Se crea en borrador para que la revises antes de emitirla.
                    </DialogDescription>
                </DialogHeader>

                {errors.domain ? (
                    <Alert variant="destructive">
                        <AlertTitle>{errors.domain}</AlertTitle>
                    </Alert>
                ) : null}

                <div className="grid gap-4">
                    <fieldset>
                        <legend className="mb-1.5 text-sm font-medium">Tipo de rectificación</legend>
                        <div className="grid gap-2" role="radiogroup">
                            {OPTIONS.map((option) => {
                                const selected = form.data.rectification_type === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() => form.setData('rectification_type', option.value)}
                                        className={cn(
                                            'rounded-lg border px-3 py-2 text-left transition-colors hover:bg-muted',
                                            selected && 'border-accent-foreground/30 bg-accent text-accent-foreground hover:bg-accent',
                                        )}
                                    >
                                        <span className="block text-sm font-medium">{option.title}</span>
                                        <span className="block text-xs text-muted-foreground">{option.description}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <FieldError message={errors.rectification_type} />
                    </fieldset>

                    <div>
                        <Label htmlFor="rectify-reason" className="mb-1.5">
                            Motivo
                        </Label>
                        <Textarea
                            id="rectify-reason"
                            rows={3}
                            value={form.data.reason}
                            maxLength={MAX_REASON_LENGTH}
                            placeholder="Por ejemplo: error en el NIF del cliente, devolución de 2 unidades…"
                            onChange={(event) => form.setData('reason', event.target.value)}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">Aparece en la rectificativa, junto al número de la factura que corrige.</p>
                        <FieldError message={errors.reason} />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button onClick={submit} disabled={form.processing || form.data.reason.trim() === ''}>
                        <FilePen aria-hidden />
                        {form.processing ? 'Creando…' : 'Crear rectificativa'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
