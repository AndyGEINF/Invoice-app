<?php

declare(strict_types=1);

namespace App\Infrastructure\Vies;

use App\Domain\Customers\Contracts\VatNumberValidator;
use App\Domain\Customers\Contracts\ViesResult;
use App\Domain\Shared\TaxId;
use Carbon\CarbonImmutable;

/**
 * VIES de mentira para tests y desarrollo sin red: siempre responde lo mismo y
 * apunta qué números se le han consultado.
 */
final class FakeVatNumberValidator implements VatNumberValidator
{
    private const string VALID = 'valid';

    private const string INVALID = 'invalid';

    private const string UNAVAILABLE = 'unavailable';

    /** @var list<string> */
    public private(set) array $checked = [];

    private function __construct(private readonly string $answer) {}

    public static function valid(): self
    {
        return new self(self::VALID);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE);
    }

    public function validate(TaxId $taxId): ViesResult
    {
        $this->checked[] = $taxId->value;
        $now = CarbonImmutable::now();

        return match ($this->answer) {
            self::VALID => ViesResult::valid($now),
            self::INVALID => ViesResult::invalid($now),
            default => ViesResult::unavailable($now),
        };
    }
}
