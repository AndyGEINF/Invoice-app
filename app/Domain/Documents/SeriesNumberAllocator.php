<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Exceptions\SeriesInactive;
use App\Domain\Documents\Exceptions\SeriesYearClosed;
use App\Domain\Shared\DocumentNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Asigna el siguiente número correlativo de una serie.
 *
 * Bloquea la fila de la serie (`SELECT … FOR UPDATE`) dentro de la transacción
 * de emisión, de modo que dos emisiones simultáneas se esperan una a la otra y
 * nunca reciben el mismo número ni dejan huecos. El índice único de
 * `documents` es la red de seguridad (decisión D5).
 */
final class SeriesNumberAllocator
{
    public function allocate(Series $series, CarbonImmutable $issueDate): DocumentNumber
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'La numeración debe asignarse dentro de la transacción de emisión: sin ella el bloqueo no protege nada.'
            );
        }

        /** @var Series $locked */
        $locked = Series::query()->whereKey($series->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->is_active) {
            throw SeriesInactive::withCode($locked->code);
        }

        $issueYear = (int) $issueDate->format('Y');

        if ($locked->resets_yearly) {
            $this->moveToYear($locked, $issueYear);
        }

        $number = $locked->next_number;

        $locked->next_number = $number + 1;
        $locked->save();

        // El modelo recibido queda al día para quien lo siga usando.
        $series->setRawAttributes($locked->getAttributes(), true);

        return DocumentNumber::of(
            $locked->prefix,
            $number,
            $locked->padding,
            $locked->resets_yearly ? $issueYear : null,
        );
    }

    /** Al empezar un año nuevo, una serie con reinicio anual vuelve al número 1. */
    private function moveToYear(Series $series, int $issueYear): void
    {
        if ($series->year === null || $series->year < $issueYear) {
            $series->year = $issueYear;
            $series->next_number = DocumentNumber::FIRST_NUMBER;

            return;
        }

        if ($series->year > $issueYear) {
            throw SeriesYearClosed::for($series->code, $issueYear, $series->year);
        }
    }
}
