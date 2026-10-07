<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Issuer\Data\IssuerData;
use App\Application\Issuer\Data\LogoFile;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Enums\VatRegime;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\TaxId;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Datos del emisor: nombre, empresa, logotipo y datos fiscales. El logotipo es
 * obligatorio mientras no haya uno guardado.
 */
final class IssuerRequest extends FormRequest
{
    public const int MAX_NAME_LENGTH = 200;

    public const int MAX_CONTACT_LENGTH = 200;

    public const int MAX_PHONE_LENGTH = 50;

    public const int MAX_FOOTER_LENGTH = 1000;

    public const int MAX_ADDRESS_LENGTH = 200;

    public const int COUNTRY_CODE_LENGTH = 2;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasLogo = trim((string) Issuer::current()->logo_path) !== '';

        return [
            'name' => ['required', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'company_name' => ['nullable', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'logo' => [
                Rule::requiredIf(! $hasLogo),
                'nullable',
                'file',
                'mimes:'.implode(',', config('invoice.issuer.logo_mimes')),
                'max:'.config('invoice.issuer.logo_max_kb'),
            ],
            'tax_id' => ['required', 'string', $this->validSpanishTaxId(...)],
            'address.street' => ['required', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'address.city' => ['required', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'address.postal_code' => ['required', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'address.province' => ['nullable', 'string', 'max:'.self::MAX_ADDRESS_LENGTH],
            'address.country' => ['nullable', 'string', 'size:'.self::COUNTRY_CODE_LENGTH],
            'vat_regime' => ['required', Rule::enum(VatRegime::class)],
            'default_irpf_rate' => ['required', 'decimal:0,2', 'between:'.Percentage::MIN.','.Percentage::MAX],
            'email' => ['nullable', 'email', 'max:'.self::MAX_CONTACT_LENGTH],
            'phone' => ['nullable', 'string', 'max:'.self::MAX_PHONE_LENGTH],
            'website' => ['nullable', 'url', 'max:'.self::MAX_CONTACT_LENGTH],
            'invoice_footer' => ['nullable', 'string', 'max:'.self::MAX_FOOTER_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'tu nombre',
            'company_name' => 'empresa o nombre comercial',
            'logo' => 'logotipo',
            'tax_id' => 'NIF',
            'address.street' => 'dirección',
            'address.city' => 'población',
            'address.postal_code' => 'código postal',
            'address.province' => 'provincia',
            'address.country' => 'país',
            'vat_regime' => 'régimen de IVA',
            'default_irpf_rate' => 'retención por defecto',
            'website' => 'web',
            'invoice_footer' => 'pie de factura',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.required' => 'Sube el logotipo: es obligatorio para emitir facturas.',
        ];
    }

    public function toIssuerData(): IssuerData
    {
        $logo = $this->file('logo');

        return IssuerData::fromArray(
            $this->validated(),
            $logo instanceof UploadedFile ? new LogoFile((string) $logo->getRealPath(), (string) $logo->extension()) : null,
        );
    }

    /** El emisor factura en territorio común: NIF, NIE o CIF españoles válidos. */
    private function validSpanishTaxId(string $attribute, mixed $value, Closure $fail): void
    {
        $taxId = TaxId::of((string) $value);

        if (! $taxId->isValid() || ! $taxId->isSpanish()) {
            $fail('El NIF no es válido: revisa la letra de control.');
        }
    }

    /** "15" → "15.00" para la regla decimal; la coma también vale. */
    protected function prepareForValidation(): void
    {
        $rate = $this->input('default_irpf_rate');

        if (is_string($rate)) {
            $this->merge(['default_irpf_rate' => str_replace(',', '.', trim($rate))]);
        }
    }
}
