<?php

declare(strict_types=1);

namespace App\Application\Series;

use App\Domain\Documents\Exceptions\SeriesLocked;
use App\Domain\Documents\Series;
use Illuminate\Support\Facades\DB;

/**
 * Borra una serie que nunca se usó. Con documentos, aunque sean borradores, solo
 * se puede desactivar (FR-032); la serie por defecto tampoco se borra.
 */
final readonly class DeleteSeries
{
    public function __invoke(Series $series): void
    {
        DB::transaction(function () use ($series): void {
            /** @var Series $series */
            $series = Series::query()->lockForUpdate()->findOrFail($series->getKey());

            if ($series->is_default) {
                throw SeriesLocked::defaultRequired($series->code);
            }

            if ($series->documents()->exists()) {
                throw SeriesLocked::hasDocuments($series->code);
            }

            $series->delete();
        });
    }
}
