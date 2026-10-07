<?php

declare(strict_types=1);

namespace App\Application\Issuer\Data;

use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\VatRegime;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\TaxId;

/**
 * Datos del emisor tal como llegan del formulario de ajustes, ya validados.
 * Sin logotipo nuevo (`logo` null) se conserva el que hubiera.
 */
final readonly class IssuerData
{
    public function __construct(
        public string $name,
        public ?string $companyName,
        public TaxId $taxId,
        public Address $address,
        public VatRegime $vatRegime,
        public Percentage $defaultIrpfRate,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?string $invoiceFooter = null,
        public ?LogoFile $logo = null,
    ) {}

    /** @param array<string, mixed> $form */
    public static function fromArray(array $form, ?LogoFile $logo = null): self
    {
        /** @var array<string, string|null> $address */
        $address = $form['address'] ?? [];

        return new self(
            name: trim((string) $form['name']),
            companyName: self::stringOrNull($form['company_name'] ?? null),
            taxId: TaxId::of((string) $form['tax_id']),
            address: Address::fromArray($address),
            vatRegime: VatRegime::from((string) $form['vat_regime']),
            defaultIrpfRate: Percentage::of((string) ($form['default_irpf_rate'] ?? Percentage::MIN)),
            email: self::stringOrNull($form['email'] ?? null),
            phone: self::stringOrNull($form['phone'] ?? null),
            website: self::stringOrNull($form['website'] ?? null),
            invoiceFooter: self::stringOrNull($form['invoice_footer'] ?? null),
            logo: $logo,
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
