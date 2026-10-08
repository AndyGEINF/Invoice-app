<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Customers\Data\CustomerData;
use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\TaxId;
use App\Http\Requests\Concerns\NormalizesPercentages;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formulario de cliente (contracts/web-routes.md). Una empresa siempre
 * lleva identificador fiscal y dirección; un particular puede no tenerlos (solo
 * recibirá facturas simplificadas). `?force=1` guarda aunque el NIF ya exista.
 */
final class CustomerRequest extends FormRequest
{
    use NormalizesPercentages;

    public const int MAX_NAME_LENGTH = 200;

    public const int MAX_TAX_ID_LENGTH = 20;

    public const int MAX_ADDRESS_LENGTH = 200;

    public const int COUNTRY_CODE_LENGTH = 2;

    public const int MAX_EMAIL_LENGTH = 255;

    public const int MAX_PHONE_LENGTH = 50;

    public const int MAX_NOTES_LENGTH = 5000;

    public const int MAX_CONTACTS = 20;

    public const int MAX_PAYMENT_TERMS_DAYS = 365;

    private const string BUSINESS = 'required_if:kind,business';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(CustomerKind::class)],
            'legal_name' => ['required', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'trade_name' => ['nullable', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'tax_id' => [self::BUSINESS, 'nullable', 'string', 'max:'.self::MAX_TAX_ID_LENGTH, $this->validTaxId(...)],
            'tax_id_type' => ['nullable', Rule::enum(TaxIdType::class)],
            'billing_address' => ['sometimes', 'array'],
            'billing_address.street' => [self::BUSINESS, 'nullable', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'billing_address.city' => [self::BUSINESS, 'nullable', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'billing_address.postal_code' => [self::BUSINESS, 'nullable', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'billing_address.province' => ['nullable', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'billing_address.country' => ['nullable', 'string', 'size:'.self::COUNTRY_CODE_LENGTH],
            'email' => ['nullable', 'email', 'max:'.self::MAX_EMAIL_LENGTH],
            'payment_terms_days' => ['required', 'integer', 'between:0,'.self::MAX_PAYMENT_TERMS_DAYS],
            'irpf_applies' => ['sometimes', 'boolean'],
            'surcharge_applies' => ['sometimes', 'boolean'],
            'default_vat_rate' => ['nullable', Rule::in(config('invoice.tax.vat_rates'))],
            'notes' => ['nullable', 'string', 'max:'.self::MAX_NOTES_LENGTH],
            'contacts' => ['sometimes', 'array', 'max:'.self::MAX_CONTACTS],
            'contacts.*.name' => ['required', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'contacts.*.email' => ['required', 'email', 'max:'.self::MAX_EMAIL_LENGTH],
            'contacts.*.phone' => ['nullable', 'string', 'max:'.self::MAX_PHONE_LENGTH],
            'contacts.*.is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'kind' => 'tipo de cliente',
            'legal_name' => 'razón social o nombre',
            'trade_name' => 'nombre comercial',
            'tax_id' => 'NIF',
            'tax_id_type' => 'tipo de identificador',
            'billing_address.street' => 'dirección',
            'billing_address.city' => 'población',
            'billing_address.postal_code' => 'código postal',
            'billing_address.province' => 'provincia',
            'billing_address.country' => 'país',
            'payment_terms_days' => 'plazo de pago',
            'default_vat_rate' => 'IVA por defecto',
            'notes' => 'notas',
            'contacts.*.name' => 'nombre del contacto',
            'contacts.*.email' => 'email del contacto',
            'contacts.*.phone' => 'teléfono del contacto',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tax_id.required_if' => 'Una empresa o profesional necesita NIF.',
            'billing_address.*.required_if' => 'Una empresa o profesional necesita la :attribute.',
        ];
    }

    public function toCustomerData(): CustomerData
    {
        return CustomerData::fromArray($this->validated());
    }

    /** "Continuar de todos modos" tras el aviso de NIF duplicado. */
    public function force(): bool
    {
        return $this->boolean('force');
    }

    /** NIF, NIE o CIF con su control correcto, o número de IVA europeo bien formado. */
    private function validTaxId(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $type = TaxIdType::tryFrom((string) $this->input('tax_id_type'));

        if (! TaxId::of((string) $value, $type)->isValid()) {
            $fail('El NIF no es válido: revisa la letra o el dígito de control.');
        }
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_vat_rate') && $this->input('default_vat_rate') !== null) {
            $this->merge(['default_vat_rate' => self::normalizePercentage($this->input('default_vat_rate'))]);
        }
    }
}
