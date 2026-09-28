<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents\Concerns;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Series;

/**
 * Props y parámetros de ruta que comparten las páginas de documentos.
 */
trait DocumentPageProps
{
    /**
     * Tipo de documento de la página: el frontend lo usa para títulos y rutas.
     *
     * @return array<string, string>
     */
    protected static function typeProps(DocumentType $type): array
    {
        return [
            'value' => $type->value,
            'label' => $type->label(),
            'segment' => $type->routeSegment(),
        ];
    }

    /**
     * Series activas del tipo, la de por defecto primero (`SeriesOption[]`).
     *
     * @return list<array<string, mixed>>
     */
    protected static function seriesOptions(DocumentType $type): array
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

    /**
     * Parámetros de las rutas `documents.*`. El tipo va como segmento
     * (`invoices`), no como valor del enum (`invoice`).
     *
     * @return array<string, string>
     */
    protected static function routeParams(DocumentType $type, ?Document $document = null): array
    {
        $params = ['type' => $type->routeSegment()];

        if ($document !== null) {
            $params['document'] = $document->id;
        }

        return $params;
    }
}
