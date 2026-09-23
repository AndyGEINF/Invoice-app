<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Documents\Invoice;

/**
 * @extends DocumentFactory<CreditNote>
 */
final class CreditNoteFactory extends DocumentFactory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'type' => CreditNote::fixedType(),
            'rectifies_id' => Invoice::factory()->issued(),
            'rectification_type' => RectificationType::Substitution,
            'rectification_reason' => 'Error en la cantidad facturada',
        ];
    }
}
