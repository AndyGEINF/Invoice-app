<?php

declare(strict_types=1);

namespace App\Application\Customers\Data;

use App\Domain\Customers\Customer;
use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\TaxId;

/**
 * Datos de alta o edición de un cliente (`CustomerForm` de
 * contracts/web-routes.md), ya validados por el form request.
 */
final readonly class CustomerData
{
    /** @param list<ContactData> $contacts */
    public function __construct(
        public CustomerKind $kind,
        public string $legalName,
        public ?string $tradeName,
        public ?TaxId $taxId,
        public Address $billingAddress,
        public ?string $email = null,
        public int $paymentTermsDays = Customer::DEFAULT_PAYMENT_TERMS_DAYS,
        public bool $irpfApplies = false,
        public bool $surchargeApplies = false,
        public ?Percentage $defaultVatRate = null,
        public ?string $notes = null,
        public array $contacts = [],
    ) {}

    /** @param array<string, mixed> $form */
    public static function fromArray(array $form): self
    {
        $taxId = self::stringOrNull($form['tax_id'] ?? null);
        $taxIdType = self::stringOrNull($form['tax_id_type'] ?? null);
        $vatRate = self::stringOrNull($form['default_vat_rate'] ?? null);
        $email = self::stringOrNull($form['email'] ?? null);

        /** @var array<string, string|null> $address */
        $address = $form['billing_address'] ?? [];

        /** @var list<array<string, mixed>> $contacts */
        $contacts = array_values($form['contacts'] ?? []);

        return new self(
            kind: CustomerKind::from((string) $form['kind']),
            legalName: trim((string) $form['legal_name']),
            tradeName: self::stringOrNull($form['trade_name'] ?? null),
            taxId: $taxId !== null ? TaxId::of($taxId, $taxIdType !== null ? TaxIdType::from($taxIdType) : null) : null,
            billingAddress: Address::fromArray($address),
            email: $email !== null ? strtolower($email) : null,
            paymentTermsDays: (int) ($form['payment_terms_days'] ?? Customer::DEFAULT_PAYMENT_TERMS_DAYS),
            irpfApplies: (bool) ($form['irpf_applies'] ?? false),
            surchargeApplies: (bool) ($form['surcharge_applies'] ?? false),
            defaultVatRate: $vatRate !== null ? Percentage::of($vatRate) : null,
            notes: self::stringOrNull($form['notes'] ?? null),
            contacts: array_map(ContactData::fromArray(...), $contacts),
        );
    }

    /**
     * Columnas de `customers` que fija el formulario. `vies_validated_at` y
     * `archived_at` los gestionan otros casos de uso.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'kind' => $this->kind,
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'tax_id' => $this->taxId?->value,
            'tax_id_type' => $this->taxId?->type,
            'billing_address' => $this->billingAddress,
            'email' => $this->email,
            'payment_terms_days' => $this->paymentTermsDays,
            'irpf_applies' => $this->irpfApplies,
            'surcharge_applies' => $this->surchargeApplies,
            'default_vat_rate' => $this->defaultVatRate,
            'notes' => $this->notes,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
