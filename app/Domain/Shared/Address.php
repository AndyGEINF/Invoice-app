<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Stringable;

/**
 * Dirección postal de un emisor o de un cliente.
 *
 * Se guarda como JSON y se congela en los snapshots al emitir: si el cliente se
 * muda, la factura ya emitida debe seguir mostrando la dirección de entonces
 * (decisión D4).
 */
final readonly class Address implements Stringable
{
    public const string DEFAULT_COUNTRY = TaxId::COUNTRY_SPAIN;

    /** Claves con las que viaja a la base de datos y al frontend. */
    public const array KEYS = ['street', 'city', 'postal_code', 'province', 'country'];

    private function __construct(
        public string $street,
        public string $city,
        public string $postalCode,
        public string $province,
        public string $country,
    ) {}

    public static function of(
        string $street,
        string $city,
        string $postalCode,
        string $province = '',
        string $country = self::DEFAULT_COUNTRY,
    ): self {
        return new self(
            trim($street),
            trim($city),
            trim($postalCode),
            trim($province),
            strtoupper(trim($country)),
        );
    }

    public static function empty(): self
    {
        return new self('', '', '', '', self::DEFAULT_COUNTRY);
    }

    /** @param array<string, string|null> $data */
    public static function fromArray(array $data): self
    {
        return self::of(
            (string) ($data['street'] ?? ''),
            (string) ($data['city'] ?? ''),
            (string) ($data['postal_code'] ?? ''),
            (string) ($data['province'] ?? ''),
            (string) ($data['country'] ?? self::DEFAULT_COUNTRY),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'province' => $this->province,
            'country' => $this->country,
        ];
    }

    /**
     * Una dirección está completa cuando sirve para facturar.
     *
     * La provincia es opcional: hay países que no la usan.
     */
    public function isComplete(): bool
    {
        return $this->street !== ''
            && $this->city !== ''
            && $this->postalCode !== ''
            && $this->country !== '';
    }

    public function isEmpty(): bool
    {
        return $this->street === ''
            && $this->city === ''
            && $this->postalCode === ''
            && $this->province === ''
            && $this->country === '';
    }

    public function isSpanish(): bool
    {
        return $this->country === TaxId::COUNTRY_SPAIN;
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /** Dirección en una línea, como se imprime en el PDF. */
    public function singleLine(): string
    {
        $locality = trim($this->postalCode.' '.$this->city);

        if ($this->province !== '' && $this->province !== $this->city) {
            $locality .= ' ('.$this->province.')';
        }

        return implode(', ', array_filter([$this->street, $locality, $this->country]));
    }

    public function __toString(): string
    {
        return $this->singleLine();
    }
}
