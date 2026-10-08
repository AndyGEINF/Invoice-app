<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Application\Customers\UpsertCustomer;
use App\Application\Customers\UpsertResult;
use App\Domain\Customers\Customer;
use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Shared\Enums\TaxIdType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta y edición de clientes.
 *
 * Si el NIF ya es de otro cliente no se guarda: se vuelve al formulario con
 * `duplicate_warning` (flash) para elegir entre abrir el existente o continuar
 * reenviando con `?force=1`.
 */
final class CustomerFormController extends Controller
{
    public function create(): Response
    {
        return self::form(null);
    }

    public function store(CustomerRequest $request, UpsertCustomer $upsert): RedirectResponse
    {
        return self::afterSave($upsert($request->toCustomerData(), null, $request->force()), 'Cliente creado.');
    }

    public function edit(Request $request, Customer $customer): Response
    {
        return self::form((new CustomerForm($customer))->resolve($request));
    }

    public function update(CustomerRequest $request, Customer $customer, UpsertCustomer $upsert): RedirectResponse
    {
        return self::afterSave($upsert($request->toCustomerData(), $customer, $request->force()), 'Cliente guardado.');
    }

    /** @param array<string, mixed>|null $customer */
    private static function form(?array $customer): Response
    {
        return Inertia::render('customers/form', [
            'customer' => $customer,
            'options' => [
                'kinds' => array_map(
                    static fn (CustomerKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
                    CustomerKind::cases(),
                ),
                'tax_id_types' => array_map(
                    static fn (TaxIdType $type): array => ['value' => $type->value, 'label' => $type->label()],
                    TaxIdType::cases(),
                ),
                'default_payment_terms_days' => Customer::DEFAULT_PAYMENT_TERMS_DAYS,
                'max_contacts' => CustomerRequest::MAX_CONTACTS,
            ],
        ]);
    }

    private static function afterSave(UpsertResult $result, string $message): RedirectResponse
    {
        if ($result->duplicate !== null) {
            Inertia::flash('duplicate_warning', [
                'id' => $result->duplicate->id,
                'legal_name' => $result->duplicate->legal_name,
                'tax_id' => $result->duplicate->tax_id,
            ]);

            return back();
        }

        Inertia::flash('success', $message);

        return to_route('customers.show', $result->customer);
    }
}
