<?php

declare(strict_types=1);

use App\Domain\Customers\Contracts\ViesResult;
use App\Domain\Documents\Contracts\RenderedPdf;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use App\Infrastructure\Time\SystemClock;
use Carbon\CarbonImmutable;

describe('Clock', function () {
    it('el contenedor resuelve el reloj del sistema', function () {
        expect(app(Clock::class))->toBeInstanceOf(SystemClock::class)
            ->and(app(Clock::class))->toBe(app(Clock::class));
    });

    it('el reloj del sistema respeta la fecha fijada en los tests', function () {
        CarbonImmutable::setTestNow('2026-09-23 15:30:00');

        $clock = new SystemClock;

        expect($clock->now()->toDateTimeString())->toBe('2026-09-23 15:30:00')
            ->and($clock->today()->toDateTimeString())->toBe('2026-09-23 00:00:00');

        CarbonImmutable::setTestNow();
    });

    it('el reloj parado solo avanza cuando se le pide', function () {
        $clock = new FrozenClock('2026-12-31 23:00:00');

        expect($clock->today()->toDateString())->toBe('2026-12-31');

        $clock->advanceDays(1);

        expect($clock->today()->toDateString())->toBe('2027-01-01');

        $clock->setTo('2026-06-15');

        expect($clock->now()->toDateString())->toBe('2026-06-15');
    });
});

describe('RenderedPdf', function () {
    it('calcula la huella SHA-256 del contenido', function () {
        $pdf = RenderedPdf::fromBytes('%PDF-1.7 prueba');

        expect($pdf->sha256)->toBe(hash('sha256', '%PDF-1.7 prueba'))
            ->and($pdf->size())->toBe(15);
    });
});

describe('ViesResult', function () {
    $checkedAt = CarbonImmutable::parse('2026-09-23');

    it('distingue válido, inválido y no disponible', function () use ($checkedAt) {
        expect(ViesResult::valid($checkedAt, 'Muster GmbH')->isConfirmed())->toBeTrue()
            ->and(ViesResult::invalid($checkedAt)->isConfirmed())->toBeFalse()
            ->and(ViesResult::unavailable($checkedAt)->isConfirmed())->toBeFalse()
            ->and(ViesResult::unavailable($checkedAt)->available)->toBeFalse()
            ->and(ViesResult::invalid($checkedAt)->available)->toBeTrue();
    });
});
