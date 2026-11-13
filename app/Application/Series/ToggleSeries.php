<?php

declare(strict_types=1);

namespace App\Application\Series;

use App\Domain\Documents\Exceptions\SeriesLocked;
use App\Domain\Documents\Series;
use Illuminate\Support\Facades\DB;

/**
 * Activa o desactiva una serie. Una serie desactivada conserva sus documentos
 * pero ya no se puede elegir al emitir. La serie por defecto no se desactiva.
 */
final readonly class ToggleSeries
{
    public function __invoke(Series $series): Series
    {
        return DB::transaction(function () use ($series): Series {
            /** @var Series $series */
            $series = Series::query()->lockForUpdate()->findOrFail($series->getKey());

            if ($series->is_active && $series->is_default) {
                throw SeriesLocked::defaultRequired($series->code);
            }

            $series->is_active = ! $series->is_active;
            $series->save();

            return $series;
        });
    }
}
