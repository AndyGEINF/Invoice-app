<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Customers\Contact;
use App\Domain\Customers\Customer;
use App\Http\Requests\CustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cliente tal como lo edita el formulario (`CustomerForm`): mismos nombres que
 * espera {@see CustomerRequest}, con textos en vez de nulos.
 *
 * @property Customer $resource
 */
final class CustomerForm extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        return [
            'id' => $customer->id,
            'kind' => $customer->kind->value,
            'legal_name' => $customer->legal_name,
            'trade_name' => $customer->trade_name ?? '',
            'tax_id' => $customer->tax_id ?? '',
            'tax_id_type' => $customer->tax_id_type?->value,
            'billing_address' => $customer->billing_address->toArray(),
            'email' => $customer->email ?? '',
            'payment_terms_days' => $customer->payment_terms_days,
            'irpf_applies' => $customer->irpf_applies,
            'surcharge_applies' => $customer->surcharge_applies,
            'default_vat_rate' => $customer->default_vat_rate !== null ? (string) $customer->default_vat_rate : null,
            'notes' => $customer->notes ?? '',
            'contacts' => $customer->contacts()->orderByDesc('is_default')->oldest()->get()
                ->map(static fn (Contact $contact): array => [
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone ?? '',
                    'is_default' => $contact->is_default,
                ])
                ->all(),
            'is_archived' => $customer->isArchived(),
        ];
    }
}
