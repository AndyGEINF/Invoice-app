<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customers\Customer;
use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\TaxIdType;
use Database\Factories\Support\SpanishTaxIds;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'kind' => CustomerKind::Business,
            'legal_name' => $this->faker->company(),
            'tax_id' => SpanishTaxIds::cif(),
            'tax_id_type' => TaxIdType::CIF,
            'billing_address' => Address::of(
                $this->faker->streetAddress(),
                $this->faker->city(),
                $this->faker->postcode(),
                $this->faker->state(),
            ),
            'email' => $this->faker->unique()->companyEmail(),
            'payment_terms_days' => Customer::DEFAULT_PAYMENT_TERMS_DAYS,
        ];
    }

    /** Particular: puede no tener NIF y nunca se le retiene IRPF. */
    public function individual(): self
    {
        return $this->state(fn (): array => [
            'kind' => CustomerKind::Individual,
            'legal_name' => $this->faker->name(),
            'tax_id' => SpanishTaxIds::nif(),
            'tax_id_type' => TaxIdType::NIF,
        ]);
    }

    /** Sin identificador fiscal: solo admite facturas simplificadas. */
    public function withoutTaxId(): self
    {
        return $this->individual()->state(fn (): array => [
            'tax_id' => null,
            'tax_id_type' => null,
        ]);
    }

    public function withIrpf(): self
    {
        return $this->state(fn (): array => ['irpf_applies' => true]);
    }

    public function withSurcharge(): self
    {
        return $this->state(fn (): array => ['surcharge_applies' => true]);
    }

    /** Empresa de otro país de la UE, validable en VIES. */
    public function intraCommunity(): self
    {
        return $this->state(fn (): array => [
            'legal_name' => 'Muster GmbH',
            'tax_id' => 'DE123456789',
            'tax_id_type' => TaxIdType::VAT_EU,
            'billing_address' => Address::of('Hauptstraße 1', 'Berlin', '10115', '', 'DE'),
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
