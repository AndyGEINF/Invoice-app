<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentEvent;
use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use App\Http\Resources\DocumentDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Detalle de un documento: cabecera, snapshots, líneas, desglose, historial,
 * envíos, documentos relacionados y qué acciones admite ahora mismo.
 */
final class DocumentShowController extends Controller
{
    use DocumentPageProps;

    public function __invoke(Request $request, DocumentType $type, Document $document): Response
    {
        $document->load(['lines', 'taxes', 'customer', 'series', 'events', 'sends']);

        return Inertia::render('documents/show', [
            'type' => self::typeProps($type),
            'document' => (new DocumentDetail($document))->resolve($request),
            'events' => $document->events
                ->map(static fn (DocumentEvent $event): array => [
                    'id' => $event->id,
                    'event' => $event->event->value,
                    'label' => $event->event->label(),
                    'payload' => $event->payload,
                    'created_at' => $event->created_at->toIso8601String(),
                ])
                ->all(),
            'sends' => $document->sends
                ->map(static fn (DocumentSend $send): array => [
                    'id' => $send->id,
                    'to' => $send->to,
                    'subject' => $send->subject,
                    'status' => $send->status->value,
                    'status_label' => $send->status->label(),
                    'error' => $send->error,
                    'queued_at' => $send->queued_at->toIso8601String(),
                    'sent_at' => $send->sent_at?->toIso8601String(),
                ])
                ->all(),
            'related' => self::related($document),
            'can' => self::abilities($document),
            'series' => self::seriesOptions($type),
        ]);
    }

    /**
     * Presupuesto de origen, factura resultante, factura rectificada y
     * rectificativas que la corrigen.
     *
     * @return array<string, mixed>
     */
    private static function related(Document $document): array
    {
        $convertedTo = Document::query()->where('converted_from_id', $document->id)->first();

        return [
            'converted_from' => self::link($document->convertedFrom),
            'converted_to' => self::link($convertedTo),
            'rectifies' => self::link($document->rectifies),
            'rectified_by' => $document->rectifications
                ->map(static fn (Document $rectification): ?array => self::link($rectification))
                ->all(),
        ];
    }

    /** @return array<string, string|null>|null */
    private static function link(?Document $document): ?array
    {
        return $document === null ? null : [
            'id' => $document->id,
            'type' => $document->type->value,
            'segment' => $document->type->routeSegment(),
            'full_number' => $document->full_number,
        ];
    }

    /**
     * Acciones que el documento admite ahora. Las que dependen de historias
     * posteriores (envío, rectificación, conversión, cobro) se activan al
     * implementarlas; hasta entonces se ofrecen como no disponibles.
     *
     * @return array<string, bool>
     */
    private static function abilities(Document $document): array
    {
        $editable = $document->isEditable();

        return [
            'edit' => $editable,
            'issue' => $editable && $document->type !== DocumentType::Quote,
            'delete' => $editable,
            'send' => false,
            'rectify' => false,
            'convert' => false,
            'mark_paid' => false,
            'unmark_paid' => false,
        ];
    }
}
