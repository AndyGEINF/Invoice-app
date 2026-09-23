<?php

declare(strict_types=1);

use App\Domain\Documents\Exceptions\SeriesInactive;
use App\Domain\Documents\Exceptions\SeriesYearClosed;
use App\Domain\Documents\Series;
use App\Domain\Documents\SeriesNumberAllocator;
use Carbon\CarbonImmutable;

function allocateIn(Series $series, string $date): string
{
    return DB::transaction(fn () => (new SeriesNumberAllocator)->allocate($series, CarbonImmutable::parse($date))->full());
}

describe('SeriesNumberAllocator', function () {
    it('asigna números consecutivos', function () {
        $series = Series::factory()->invoices()->create();

        expect(allocateIn($series, '2026-09-24'))->toBe('F2026-0001')
            ->and(allocateIn($series, '2026-09-24'))->toBe('F2026-0002')
            ->and(allocateIn($series, '2026-09-25'))->toBe('F2026-0003')
            ->and($series->refresh()->next_number)->toBe(4);
    });

    it('reinicia la numeración al empezar un año nuevo', function () {
        $series = Series::factory()->invoices()->create(['year' => 2026, 'next_number' => 57]);

        expect(allocateIn($series, '2026-12-31'))->toBe('F2026-0057')
            ->and(allocateIn($series, '2027-01-02'))->toBe('F2027-0001')
            ->and($series->refresh()->year)->toBe(2027);
    });

    it('no admite emitir con fecha de un año que la serie ya cerró', function () {
        $series = Series::factory()->invoices()->create(['year' => 2027, 'next_number' => 3]);

        allocateIn($series, '2026-12-31');
    })->throws(SeriesYearClosed::class);

    it('ignora el año en una serie sin reinicio anual', function () {
        $series = Series::factory()->create(['code' => 'R', 'prefix' => 'R', 'resets_yearly' => false, 'next_number' => 7]);

        expect(allocateIn($series, '2026-09-24'))->toBe('R-0007')
            ->and(allocateIn($series, '2027-01-01'))->toBe('R-0008');
    });

    it('respeta el número inicial y el relleno de la serie', function () {
        $series = Series::factory()->create(['code' => 'T', 'prefix' => 'TALLER', 'next_number' => 100, 'year' => 2026]);

        expect(allocateIn($series, '2026-09-24'))->toBe('TALLER2026-0100');
    });

    it('no numera en una serie desactivada', function () {
        allocateIn(Series::factory()->invoices()->inactive()->create(), '2026-09-24');
    })->throws(SeriesInactive::class);

    it('bloquea la fila de la serie con FOR UPDATE', function () {
        $series = Series::factory()->invoices()->create();
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        allocateIn($series, '2026-09-24');

        expect(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'from "series"') && str_contains($sql, 'for update')))
            ->toBeTrue();
    });

    // La exigencia de transacción abierta no se puede probar aquí: RefreshDatabase
    // ya envuelve cada test en una transacción. La cubre el test de concurrencia,
    // que emite desde procesos independientes.
});
