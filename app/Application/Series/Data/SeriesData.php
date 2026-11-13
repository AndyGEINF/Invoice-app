<?php

declare(strict_types=1);

namespace App\Application\Series\Data;

use App\Domain\Documents\Enums\DocumentType;

/** Datos de una serie tal como llegan del formulario de Ajustes > Series, ya validados. */
final readonly class SeriesData
{
    public function __construct(
        public DocumentType $documentType,
        public string $code,
        public string $prefix,
        public int $padding,
        public int $nextNumber,
        public bool $resetsYearly,
        public bool $isDefault,
        public bool $isActive,
    ) {}
}
