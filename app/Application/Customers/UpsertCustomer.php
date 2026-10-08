<?php

declare(strict_types=1);

namespace App\Application\Customers;

use App\Application\Customers\Data\ContactData;
use App\Application\Customers\Data\CustomerData;
use App\Domain\Customers\Customer;
use App\Events\CustomerSaved;
use Illuminate\Support\Facades\DB;

/**
 * Crea o modifica un cliente con sus contactos.
 *
 * Si ya hay otro cliente con el mismo identificador fiscal no guarda nada y
 * devuelve ese cliente, para que el usuario elija entre abrir el existente o
 * continuar (`$force`). No es un error: dos delegaciones de una misma empresa
 * pueden compartir CIF.
 *
 * Cambiar el identificador fiscal anula la validación VIES anterior.
 */
final readonly class UpsertCustomer
{
    public function __invoke(CustomerData $data, ?Customer $customer = null, bool $force = false): UpsertResult
    {
        if (! $force && ($duplicate = $this->findDuplicate($data, $customer)) !== null) {
            return UpsertResult::duplicateOf($duplicate);
        }

        $customer ??= new Customer;

        $saved = DB::transaction(function () use ($data, $customer): Customer {
            $customer->fill($data->toAttributes());
            $taxIdChanged = $customer->isDirty('tax_id');

            if ($taxIdChanged) {
                $customer->vies_validated_at = null;
            }

            $customer->save();
            $this->syncContacts($customer, $data->contacts);

            CustomerSaved::dispatch($customer->id, $taxIdChanged);

            return $customer;
        });

        return UpsertResult::saved($saved->refresh());
    }

    private function findDuplicate(CustomerData $data, ?Customer $customer): ?Customer
    {
        if ($data->taxId === null) {
            return null;
        }

        return Customer::query()
            ->where('tax_id', $data->taxId->value)
            ->when($customer?->exists, fn ($query) => $query->whereKeyNot($customer->id))
            ->oldest()
            ->first();
    }

    /**
     * Sustituye los contactos por los del formulario. Siempre queda uno por
     * defecto si hay alguno: el primero marcado o, si no hay marca, el primero.
     *
     * @param  list<ContactData>  $contacts
     */
    private function syncContacts(Customer $customer, array $contacts): void
    {
        $customer->contacts()->delete();

        $defaultIndex = 0;

        foreach ($contacts as $index => $contact) {
            if ($contact->isDefault) {
                $defaultIndex = $index;
                break;
            }
        }

        foreach ($contacts as $index => $contact) {
            $customer->contacts()->create($contact->toAttributes($index === $defaultIndex));
        }
    }
}
