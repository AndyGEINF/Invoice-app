<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Customers\Contact;
use App\Domain\Customers\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del listado de clientes (`CustomerRow`). Espera la relación `contacts`
 * cargada: el email y el teléfono salen del contacto por defecto si lo hay.
 *
 * @property Customer $resource
 */
final class CustomerRow extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = $this->resource;
        $contact = self::defaultContact($customer);

        return [
            'id' => $customer->id,
            'kind' => $customer->kind->value,
            'kind_label' => $customer->kind->label(),
            'legal_name' => $customer->legal_name,
            'trade_name' => $customer->trade_name,
            'display_name' => $customer->displayName(),
            'tax_id' => $customer->tax_id,
            'email' => $contact->email ?? $customer->email,
            'phone' => $contact?->phone,
            'city' => $customer->billing_address->city !== '' ? $customer->billing_address->city : null,
            'is_archived' => $customer->isArchived(),
        ];
    }

    private static function defaultContact(Customer $customer): ?Contact
    {
        $contacts = $customer->relationLoaded('contacts') ? $customer->contacts : $customer->contacts()->get();

        return $contacts->firstWhere('is_default', true) ?? $contacts->first();
    }
}
