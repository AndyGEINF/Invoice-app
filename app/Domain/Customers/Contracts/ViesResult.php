<?php

declare(strict_types=1);

namespace App\Domain\Customers\Contracts;

use Carbon\CarbonImmutable;

/**
 * Respuesta de VIES sobre un número de IVA intracomunitario.
 *
 * "No disponible" no es lo mismo que "no válido": VIES cae a menudo y la
 * emisión nunca se bloquea por ello.
 */
final readonly class ViesResult
{
    private function __construct(
        public bool $available,
        public bool $valid,
        public ?string $name,
        public CarbonImmutable $checkedAt,
    ) {}

    public static function valid(CarbonImmutable $checkedAt, ?string $name = null): self
    {
        return new self(true, true, $name, $checkedAt);
    }

    public static function invalid(CarbonImmutable $checkedAt): self
    {
        return new self(true, false, null, $checkedAt);
    }

    public static function unavailable(CarbonImmutable $checkedAt): self
    {
        return new self(false, false, null, $checkedAt);
    }

    /** Solo una respuesta válida y disponible confirma el número. */
    public function isConfirmed(): bool
    {
        return $this->available && $this->valid;
    }
}
