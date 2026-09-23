<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use App\Domain\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;

/**
 * Reloj parado para tests: devuelve siempre el instante fijado hasta que se
 * mueve explícitamente.
 */
final class FrozenClock implements Clock
{
    private CarbonImmutable $now;

    public function __construct(CarbonImmutable|string $now)
    {
        $this->now = $now instanceof CarbonImmutable ? $now : CarbonImmutable::parse($now);
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function today(): CarbonImmutable
    {
        return $this->now->startOfDay();
    }

    public function setTo(CarbonImmutable|string $now): void
    {
        $this->now = $now instanceof CarbonImmutable ? $now : CarbonImmutable::parse($now);
    }

    public function advanceDays(int $days): void
    {
        $this->now = $this->now->addDays($days);
    }
}
