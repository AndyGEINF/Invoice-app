<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use App\Domain\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;

/**
 * Reloj real. Respeta `Carbon::setTestNow()`, que es lo que usan los helpers de
 * tiempo de Laravel en los tests.
 */
final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }
}
