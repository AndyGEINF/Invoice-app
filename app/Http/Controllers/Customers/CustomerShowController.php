<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Domain\Customers\Customer;
use App\Domain\Documents\Document;
use App\Domain\Shared\Address;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerForm;
use App\Http\Resources\DocumentRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ficha de un cliente: sus datos, sus documentos y el estado de VIES. */
final class CustomerShowController extends Controller
{
    /** Documentos del cliente que se cargan en la ficha. */
    private const int DOCUMENTS_LIMIT = 50;

    /** Estados de la validación VIES que muestra la ficha. */
    public const string VIES_NOT_APPLICABLE = 'not_applicable';

    public const string VIES_VALIDATED = 'validated';

    public const string VIES_PENDING = 'pending';

    public function __invoke(Request $request, Customer $customer): Response
    {
        $customer->load('contacts');

        return Inertia::render('customers/show', [
            'customer' => [
                ...(new CustomerForm($customer))->resolve($request),
                'display_name' => $customer->displayName(),
                'kind_label' => $customer->kind->label(),
                'tax_id_type_label' => $customer->tax_id_type?->label(),
                'address_line' => self::addressLine($customer->billing_address),
            ],
            'documents' => Document::query()
                ->where('customer_id', $customer->id)
                ->with('customer')
                ->latest('updated_at')
                ->limit(self::DOCUMENTS_LIMIT)
                ->get()
                ->map(fn (Document $document): array => (new DocumentRow($document))->resolve($request))
                ->all(),
            'vies' => [
                'validated_at' => $customer->vies_validated_at?->toIso8601String(),
                'status' => self::viesStatus($customer),
            ],
        ]);
    }

    /** Dirección en una línea; el país solo si no es España, como en el PDF. */
    private static function addressLine(Address $address): ?string
    {
        if ($address->street === '' && $address->city === '' && $address->postalCode === '') {
            return null;
        }

        return $address->isSpanish()
            ? Address::of($address->street, $address->city, $address->postalCode, $address->province, '')->singleLine()
            : $address->singleLine();
    }

    private static function viesStatus(Customer $customer): string
    {
        if ($customer->taxId()?->isEuVat() !== true) {
            return self::VIES_NOT_APPLICABLE;
        }

        return $customer->vies_validated_at !== null ? self::VIES_VALIDATED : self::VIES_PENDING;
    }
}
