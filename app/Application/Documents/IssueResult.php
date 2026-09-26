<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Domain\Documents\Document;

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
}
