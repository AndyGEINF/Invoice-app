import { useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';

import { FieldError } from '@/components/documents/LineEditor';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { documentUrl } from '@/lib/documents';
import type { DocumentTypeProps, SendPrefill } from '@/types/documents';

/** Lo mismo que admite el servidor (`SendDocumentRequest`). */
const MAX_SUBJECT_LENGTH = 200;
const MAX_BODY_LENGTH = 5000;

/** "a@x.es, b@y.es" → ["a@x.es", "b@y.es"]. También vale con punto y coma o espacios. */
function emailList(value: string): string[] {
    return value
        .split(/[\s,;]+/)
        .map((email) => email.trim())
        .filter(Boolean);
}

/** Primer error de una lista: `to` o el de cualquiera de sus direcciones (`to.1`). */
function listError(errors: Record<string, string>, field: string): string | undefined {
    return errors[field] ?? Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1];
}

/**
 * Enviar el documento por email con el PDF adjunto. Se abre con el contacto del
 * cliente y un texto propuesto, o con los datos de un envío fallido para
 * reintentarlo (y corregir la dirección si era el problema).
 */
export function SendDialog({
    onClose,
    type,
    documentId,
    fullNumber,
    prefill,
}: {
    onClose: () => void;
    type: DocumentTypeProps;
    documentId: string;
    fullNumber: string | null;
    prefill: SendPrefill;
}) {
    const form = useForm({
        to: prefill.to.join(', '),
        cc: prefill.cc.join(', '),
        subject: prefill.subject,
        body: prefill.body,
    });
    const errors = form.errors as Record<string, string>;

    function submit() {
        form.transform((data) => ({ ...data, to: emailList(data.to), cc: emailList(data.cc) }));
        form.post(documentUrl(type, documentId, 'email'), { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog open onOpenChange={(open) => (open ? null : onClose())}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Enviar {fullNumber ?? type.label.toLowerCase()} por email</DialogTitle>
                    <DialogDescription>El PDF va adjunto. Si el cliente responde, la respuesta llega a tu email.</DialogDescription>
                </DialogHeader>

                {errors.domain ? (
                    <Alert variant="destructive">
                        <AlertTitle>{errors.domain}</AlertTitle>
                    </Alert>
                ) : null}

                <div className="grid gap-4">
                    <div>
                        <Label htmlFor="send-to" className="mb-1.5">
                            Para
                        </Label>
                        <Input id="send-to" type="text" inputMode="email" value={form.data.to} placeholder="cliente@empresa.es" onChange={(event) => form.setData('to', event.target.value)} />
                        <FieldError message={listError(errors, 'to')} />
                    </div>
                    <div>
                        <Label htmlFor="send-cc" className="mb-1.5">
                            Copia <span className="font-normal text-muted-foreground">(opcional)</span>
                        </Label>
                        <Input id="send-cc" type="text" inputMode="email" value={form.data.cc} placeholder="Separa varias direcciones con comas" onChange={(event) => form.setData('cc', event.target.value)} />
                        <FieldError message={listError(errors, 'cc')} />
                    </div>
                    <div>
                        <Label htmlFor="send-subject" className="mb-1.5">
                            Asunto
                        </Label>
                        <Input id="send-subject" value={form.data.subject} maxLength={MAX_SUBJECT_LENGTH} onChange={(event) => form.setData('subject', event.target.value)} />
                        <FieldError message={errors.subject} />
                    </div>
                    <div>
                        <Label htmlFor="send-body" className="mb-1.5">
                            Mensaje
                        </Label>
                        <Textarea id="send-body" rows={8} value={form.data.body} maxLength={MAX_BODY_LENGTH} onChange={(event) => form.setData('body', event.target.value)} />
                        <FieldError message={errors.body} />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Mail aria-hidden />
                        {form.processing ? 'Enviando…' : 'Enviar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
