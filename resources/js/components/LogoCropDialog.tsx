import { Crop, ZoomIn, ZoomOut } from 'lucide-react';
import { useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/** Lado del PNG que se guarda: de sobra para la cabecera de la factura y el avatar. */
const OUTPUT_SIZE_PX = 512;

/** Por debajo de 1 se aleja: el logotipo entero cabe en el círculo y sobra margen transparente. */
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 3;
const ZOOM_STEP = 0.05;

const OUTPUT_TYPE = 'image/png';
const OUTPUT_NAME = 'logotipo.png';

function loadImage(source: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('No se pudo leer la imagen.'));
        image.src = source;
    });
}

/**
 * Recorta lo que queda dentro del círculo a un PNG cuadrado. Se dibuja la imagen
 * entera desplazada y escalada (no un trozo de ella), así funciona también
 * cuando el recorte se sale de la imagen al alejar el zoom.
 */
async function cropToFile(source: string, area: Area): Promise<File> {
    const image = await loadImage(source);
    const canvas = document.createElement('canvas');
    canvas.width = OUTPUT_SIZE_PX;
    canvas.height = OUTPUT_SIZE_PX;
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('El navegador no permite recortar imágenes.');
    }

    const scale = OUTPUT_SIZE_PX / area.width;
    context.drawImage(image, -area.x * scale, -area.y * scale, image.naturalWidth * scale, image.naturalHeight * scale);

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, OUTPUT_TYPE));

    if (!blob) {
        throw new Error('No se pudo generar el logotipo.');
    }

    return new File([blob], OUTPUT_NAME, { type: OUTPUT_TYPE });
}

/**
 * Ventana para encajar el logotipo: un círculo marca el espacio que ocupará.
 * Se arrastra para moverlo y se acerca o aleja con la rueda o el control de zoom.
 */
export function LogoCropDialog({ source, onCancel, onConfirm }: { source: string | null; onCancel: () => void; onConfirm: (file: File) => void }) {
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [area, setArea] = useState<Area | null>(null);
    const [working, setWorking] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function reset() {
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setArea(null);
        setError(null);
    }

    async function confirm() {
        if (!source || !area) {
            return;
        }

        setWorking(true);

        try {
            onConfirm(await cropToFile(source, area));
            reset();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'No se pudo recortar la imagen.');
        } finally {
            setWorking(false);
        }
    }

    function cancel() {
        reset();
        onCancel();
    }

    return (
        <Dialog open={source !== null} onOpenChange={(open) => (open ? null : cancel())}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Encaja tu logotipo</DialogTitle>
                    <DialogDescription>Lo que quede dentro del círculo es lo que se verá. Arrástralo para moverlo y usa el zoom para ajustarlo.</DialogDescription>
                </DialogHeader>

                <div className="relative h-72 overflow-hidden rounded-lg bg-muted">
                    {source ? (
                        <Cropper
                            image={source}
                            crop={crop}
                            zoom={zoom}
                            minZoom={MIN_ZOOM}
                            maxZoom={MAX_ZOOM}
                            zoomSpeed={ZOOM_STEP * 2}
                            aspect={1}
                            cropShape="round"
                            showGrid={false}
                            restrictPosition={false}
                            objectFit="contain"
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={(_, pixels) => setArea(pixels)}
                        />
                    ) : null}
                </div>

                <label className="flex items-center gap-3 text-muted-foreground">
                    <ZoomOut className="size-4 shrink-0" aria-hidden />
                    <input
                        type="range"
                        min={MIN_ZOOM}
                        max={MAX_ZOOM}
                        step={ZOOM_STEP}
                        value={zoom}
                        onChange={(event) => setZoom(Number(event.target.value))}
                        aria-label="Zoom"
                        className="w-full accent-primary"
                    />
                    <ZoomIn className="size-4 shrink-0" aria-hidden />
                </label>

                {error ? <p className="text-sm text-destructive">{error}</p> : null}

                <DialogFooter>
                    <Button variant="outline" onClick={cancel}>
                        Cancelar
                    </Button>
                    <Button onClick={confirm} disabled={working || !area}>
                        <Crop aria-hidden />
                        {working ? 'Recortando…' : 'Usar este recorte'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
