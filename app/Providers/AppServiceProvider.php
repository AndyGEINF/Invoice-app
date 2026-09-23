<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Shared\Contracts\Clock;
use App\Domain\Tax\SpanishTaxCalculator;
use App\Domain\Tax\TaxCalculator;
use App\Infrastructure\Time\SystemClock;
use Illuminate\Support\ServiceProvider;

/**
 * Conecta los puertos del dominio con sus adaptadores.
 *
 * El dominio solo conoce interfaces; aquí se decide qué implementación usa cada
 * entorno (constitución, principio III). Los adaptadores de PDF, almacenamiento
 * y VIES se registran cuando se implementan (T074, T086).
 */
final class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Clock::class => SystemClock::class,
        TaxCalculator::class => SpanishTaxCalculator::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
