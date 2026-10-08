<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Customers\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cliente en el selector del documento (`CustomerOption`): lo que se ve al
 * buscarlo y lo que cambia el formulario al elegirlo (retención y recargo).
 * Lo devuelven `/customers/search` y la página del documento para el actual.
 *
 * @property Customer $resource
 */
final class CustomerOption extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        return [
            'id' => $customer->id,
            'legal_name' => $customer->legal_name,
            'trade_name' => $customer->trade_name,
            'tax_id' => $customer->tax_id,
            'email' => $customer->email,
            'irpf_applies' => $customer->appliesIrpf(),
            'surcharge_applies' => $customer->surcharge_applies,
        ];
    }
}
