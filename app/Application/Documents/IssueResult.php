<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Document;
use App\Domain\Shared\Money;

/**
 * Factura recién emitida y los avisos que no impiden emitirla.
 */
final readonly class IssueResult
{
    /** Factura simplificada por encima del límite legal habitual. */
    public const string WARNING_SIMPLIFIED_OVER_LIMIT = 'simplified_over_limit';

    /** @param list<string> $warnings */
    public function __construct(
        public Document $invoice,
        public array $warnings = [],
    ) {}

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * Los avisos redactados para el usuario.
     *
     * @return list<string>
     */
    public function warningMessages(): array
    {
        return array_map(static fn (string $warning): string => match ($warning) {
            self::WARNING_SIMPLIFIED_OVER_LIMIT => sprintf(
                'Factura simplificada por encima de %s: revisa si debería llevar los datos fiscales del cliente.',
                Money::fromCents((int) config('invoice.invoice.simplified_limit_cents'))->format(),
            ),
            default => $warning,
        }, $this->warnings);
    }
}
