<?php

declare(strict_types=1);

namespace App\Application\Customers\Data;

/**
 * Persona de contacto tal como llega del formulario del cliente.
 */
final readonly class ContactData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public bool $isDefault = false,
    ) {}

    /** @param array<string, mixed> $form */
    public static function fromArray(array $form): self
    {
        $phone = trim((string) ($form['phone'] ?? ''));

        return new self(
            name: trim((string) $form['name']),
            email: strtolower(trim((string) $form['email'])),
            phone: $phone !== '' ? $phone : null,
            isDefault: (bool) ($form['is_default'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(bool $isDefault): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_default' => $isDefault,
        ];
    }
}
