<?php

declare(strict_types=1);

namespace App\Http\Pages;

use App\Domain\Customers\Customer;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentEvent;
use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Http\Presenters\PrintableDocument;
use App\Http\Resources\DocumentDetail;
use App\Http\Resources\DraftFormResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página única de un documento (`documents/show`): el documento tipo papel a la
 * izquierda, con el mismo contenido que el PDF, y el panel de acciones a la
 * derecha.
 *
 * - Documento nuevo (aún sin guardar): papel editable vacío.
 * - Borrador: papel editable; cada guardado recalcula en el servidor.
 * - Emitido: solo lectura.
 */
final class DocumentPage
{
    /** Clientes que se ofrecen en el selector hasta que llegue la búsqueda (T077). */
    public const int MAX_CUSTOMERS = 500;

    public static function render(Request $request, DocumentType $type, ?Document $document, ?string $customerId = null): Response
    {
        $editable = $document === null || $document->isEditable();
        $paperSource = $document ?? self::unsaved($type, $customerId);

        return Inertia::render('documents/show', [
            'type' => self::typeProps($type),
            'document' => $document === null ? null : (new DocumentDetail(self::loaded($document)))->resolve($request),
            'paper' => PrintableDocument::from($paperSource, embedLogo: false),
            'form' => ! $editable ? null : ($document === null
                ? DraftFormResource::blank(Issuer::current()->default_currency, Issuer::current()->default_irpf_rate, $customerId)
                : (new DraftFormResource($document))->resolve($request)),
            'customers' => $editable ? self::customerOptions() : [],
            'defaults' => self::defaults(),
            'events' => $document === null ? [] : self::events($document),
            'sends' => $document === null ? [] : self::sends($document),
            'related' => $document === null ? null : self::related($document),
            'can' => self::abilities($type, $document),
            'series' => self::seriesOptions($type),
        ]);
    }

    /** @return array<string, string> */
    public static function typeProps(DocumentType $type): array
    {
        return [
            'value' => $type->value,
            'label' => $type->label(),
            'segment' => $type->routeSegment(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function seriesOptions(DocumentType $type): array
    {
        return Series::query()
            ->forType($type)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get()
            ->map(static fn (Series $series): array => [
                'id' => $series->id,
                'code' => $series->code,
                'prefix' => $series->prefix,
                'is_default' => $series->is_default,
            ])
            ->all();
    }

    private static function loaded(Document $document): Document
    {
        return $document->loadMissing(['lines', 'taxes', 'customer', 'series', 'events', 'sends']);
    }

    /** Documento sin guardar del tipo dado, solo para pintar el papel vacío. */
    private static function unsaved(DocumentType $type, ?string $customerId): Document
    {
        $class = Document::classFor($type);
        $document = new $class;
        $document->setRelation('lines', collect());
        $document->setRelation('taxes', collect());
        $document->setRelation('rectifies', null);
        $document->setRelation('customer', $customerId !== null ? Customer::query()->find($customerId) : null);

        return $document;
    }

    /**
     * Clientes activos para el selector, con lo que cambia el formulario: si se
     * les aplica retención o recargo.
     *
     * @return list<array<string, mixed>>
     */
    private static function customerOptions(): array
    {
        return Customer::query()
            ->whereNull('archived_at')
            ->orderBy('legal_name')
            ->limit(self::MAX_CUSTOMERS)
            ->get()
            ->map(static fn (Customer $customer): array => [
                'id' => $customer->id,
                'legal_name' => $customer->legal_name,
                'tax_id' => $customer->tax_id,
                'irpf_applies' => $customer->appliesIrpf(),
                'surcharge_applies' => $customer->surcharge_applies,
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function defaults(): array
    {
        $issuer = Issuer::current();

        return [
            'vat_rate' => (string) config('invoice.tax.default_vat_rate'),
            'irpf_rate' => (string) $issuer->default_irpf_rate,
            'currency' => $issuer->default_currency,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function events(Document $document): array
    {
        return $document->events
            ->map(static fn (DocumentEvent $event): array => [
                'id' => $event->id,
                'event' => $event->event->value,
                'label' => $event->event->label(),
                'payload' => $event->payload,
                'created_at' => $event->created_at->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private static function sends(Document $document): array
    {
        return $document->sends
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
            ->all();
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
    private static function abilities(DocumentType $type, ?Document $document): array
    {
        $editable = $document === null || $document->isEditable();
        $saved = $document !== null;

        return [
            'edit' => $editable,
            'issue' => $saved && $editable && $type !== DocumentType::Quote,
            'delete' => $saved && $editable,
            'send' => false,
            'rectify' => false,
            'convert' => false,
            'mark_paid' => false,
            'unmark_paid' => false,
        ];
    }
}
