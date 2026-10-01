import { useForm } from '@inertiajs/react';
import { Send, TriangleAlert } from 'lucide-react';

import { FieldError } from '@/components/documents/LineEditor';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { todayIso } from '@/lib/dates';
import { documentUrl } from '@/lib/documents';
import type { DocumentTypeProps, SeriesOption } from '@/types/documents';

/**
 * Confirmación de emisión: serie, fecha de expedición (hoy o anterior) y fecha
 * de operación si es distinta. El número lo asigna el servidor al emitir; los
 * errores de negocio (fecha anterior a la última de la serie…) llegan aquí.
 */
export function IssueDialog({
    open,
    onOpenChange,
    type,
    documentId,
    series,
    simplified,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    type: DocumentTypeProps;
    documentId: string;
    series: SeriesOption[];
    /** Sin NIF del cliente: se emitirá como factura simplificada. */
    simplified: boolean;
}) {
    const defaultSeries = series.find((item) => item.is_default) ?? series[0];
    const form = useForm({
        series_id: defaultSeries?.id ?? '',
        issue_date: todayIso(),
        operation_date: '',
    });
    const errors = form.errors as Record<string, string>;

    function submit() {
        form.transform((data) => ({ ...data, operation_date: data.operation_date || null }));
        form.post(documentUrl(type, documentId, 'issue'), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Emitir {type.label.toLowerCase()}</DialogTitle>
                    <DialogDescription>
                        Al emitir se le asigna el número y queda cerrada: ya no se podrá modificar ni borrar. Para corregirla habrá que hacer
                        una rectificativa.
                    </DialogDescription>
                </DialogHeader>

                {simplified ? (
                    <Alert className="border-warning/30 bg-warning-soft text-warning-strong">
                        <TriangleAlert aria-hidden />
                        <AlertTitle>Se emitirá como factura simplificada</AlertTitle>
                        <AlertDescription className="text-current">El cliente no tiene NIF. Si lo tiene, añádelo antes de emitir.</AlertDescription>
                    </Alert>
                ) : null}

                {errors.domain ? (
                    <Alert variant="destructive">
                        <AlertTitle>{errors.domain}</AlertTitle>
                    </Alert>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2">
                    {series.length > 1 ? (
                        <div className="sm:col-span-2">
                            <Label htmlFor="issue-series" className="mb-1.5">
                                Serie
                            </Label>
                            <Select value={form.data.series_id} onValueChange={(value) => form.setData('series_id', value)}>
                                <SelectTrigger id="issue-series" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {series.map((item) => (
                                        <SelectItem key={item.id} value={item.id}>
                                            {item.code} · {item.prefix}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={errors.series_id} />
                        </div>
                    ) : null}
                    <div>
                        <Label htmlFor="issue-date" className="mb-1.5">
                            Fecha de expedición
                        </Label>
                        <Input
                            id="issue-date"
                            type="date"
                            max={todayIso()}
                            value={form.data.issue_date}
                            onChange={(event) => form.setData('issue_date', event.target.value)}
                        />
                        <FieldError message={errors.issue_date} />
                    </div>
                    <div>
                        <Label htmlFor="operation-date" className="mb-1.5">
                            Fecha de operación <span className="font-normal text-muted-foreground">(si es distinta)</span>
                        </Label>
                        <Input
                            id="operation-date"
                            type="date"
                            value={form.data.operation_date}
                            onChange={(event) => form.setData('operation_date', event.target.value)}
                        />
                        <FieldError message={errors.operation_date} />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Send aria-hidden />
                        {form.processing ? 'Emitiendo…' : 'Emitir'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
