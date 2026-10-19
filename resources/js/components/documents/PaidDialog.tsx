import { useForm } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';

import { FieldError } from '@/components/documents/LineEditor';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { todayIso } from '@/lib/dates';
import { documentUrl } from '@/lib/documents';
import type { DocumentTypeProps } from '@/types/documents';

/** Lo mismo que admite el servidor (`MarkPaidRequest::MAX_NOTE_LENGTH`). */
const MAX_NOTE_LENGTH = 200;

/**
 * Marcar como cobrada: cuándo se cobró y, si se quiere, una nota corta
 * ("Transferencia", "Bizum"…). Es solo un marcador: la aplicación no registra
 * importes ni formas de pago.
 */
export function PaidDialog({
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
    const form = useForm({ paid_on: todayIso(), note: '' });
    const errors = form.errors as Record<string, string>;

    function submit() {
        form.post(documentUrl(type, documentId, 'paid'), {
            preserveScroll: true,
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
                    <DialogTitle>Marcar {fullNumber ?? 'la factura'} como cobrada</DialogTitle>
                    <DialogDescription>Dejará de contar como pendiente o vencida. Si te equivocas, puedes desmarcarla después.</DialogDescription>
                </DialogHeader>

                {errors.domain ? (
                    <Alert variant="destructive">
                        <AlertTitle>{errors.domain}</AlertTitle>
                    </Alert>
                ) : null}

                <div className="grid gap-4">
                    <div>
                        <Label htmlFor="paid-on" className="mb-1.5">
                            Fecha de cobro
                        </Label>
                        <Input id="paid-on" type="date" max={todayIso()} value={form.data.paid_on} onChange={(event) => form.setData('paid_on', event.target.value)} />
                        <FieldError message={errors.paid_on} />
                    </div>
                    <div>
                        <Label htmlFor="paid-note" className="mb-1.5">
                            Nota <span className="font-normal text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input
                            id="paid-note"
                            value={form.data.note}
                            maxLength={MAX_NOTE_LENGTH}
                            placeholder="Transferencia, Bizum, efectivo…"
                            onChange={(event) => form.setData('note', event.target.value)}
                        />
                        <FieldError message={errors.note} />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <CircleCheck aria-hidden />
                        {form.processing ? 'Guardando…' : 'Marcar como cobrada'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
