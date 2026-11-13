<?php

declare(strict_types=1);

namespace App\Application\Series;

use App\Application\Series\Data\SeriesData;
use App\Domain\Documents\Exceptions\SeriesLocked;
use App\Domain\Documents\Series;
use App\Domain\Documents\SeriesNumberAllocator;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Crea o modifica una serie de numeración.
 *
 * - Con documentos emitidos, el formato del número (código, prefijo, dígitos y
 *   reinicio anual) queda fijado y el contador solo puede subir (FR-032).
 * - Marcar una serie como por defecto se la quita a la anterior del mismo tipo;
 *   quitársela sin dar otra no se permite, porque los borradores nuevos la usan.
 *
 * La fila se bloquea como en la emisión: nadie puede emitir en la serie
 * mientras cambia su contador.
 */
final readonly class UpsertSeries
{
    public function __construct(private Clock $clock) {}

    public function __invoke(?Series $series, SeriesData $data): Series
    {
        if ($data->isDefault && ! $data->isActive) {
            throw SeriesLocked::defaultMustBeActive();
        }

        return DB::transaction(function () use ($series, $data): Series {
            $year = $this->clock->today()->year;
            $series = $series === null ? new Series : Series::query()->lockForUpdate()->findOrFail($series->getKey());

            if ($series->exists) {
                $this->assertAllowed($series, $data, $year);
            }

            $counterChanged = $series->exists
                ? $data->nextNumber !== $series->nextNumberIn($year)
                : $data->nextNumber !== DocumentNumber::FIRST_NUMBER;

            if ($data->isDefault) {
                Series::query()
                    ->forType($data->documentType)
                    ->where('is_default', true)
                    ->when($series->exists, fn ($query) => $query->whereKeyNot($series->getKey()))
                    ->update(['is_default' => false]);
            }

            $series->fill([
                'document_type' => $data->documentType,
                'code' => $data->code,
                'prefix' => $data->prefix,
                'padding' => $data->padding,
                'next_number' => $data->nextNumber,
                'resets_yearly' => $data->resetsYearly,
                'is_default' => $data->isDefault,
                'is_active' => $data->isActive,
            ]);

            // Un contador elegido a mano vale para este año. Sin fijarlo, la primera
            // emisión del año lo devolvería al 1 ({@see SeriesNumberAllocator}).
            if ($counterChanged && $data->resetsYearly) {
                $series->year = $year;
            }

            $series->save();

            return $series;
        });
    }

    private function assertAllowed(Series $series, SeriesData $data, int $year): void
    {
        if ($series->is_default && ! $data->isDefault) {
            throw SeriesLocked::defaultRequired($series->code);
        }

        if ($data->documentType !== $series->document_type && $series->documents()->exists()) {
            throw SeriesLocked::typeFixed($series->code);
        }

        if (! $series->hasIssuedDocuments()) {
            return;
        }

        $formatChanged = $data->code !== $series->code
            || $data->prefix !== $series->prefix
            || $data->padding !== $series->padding
            || $data->resetsYearly !== $series->resets_yearly;

        if ($formatChanged) {
            throw SeriesLocked::formatFixed($series->code);
        }

        $next = $series->nextNumberIn($year);

        if ($data->nextNumber < $next) {
            throw SeriesLocked::counterBackwards($series->code, $next);
        }
    }
}
