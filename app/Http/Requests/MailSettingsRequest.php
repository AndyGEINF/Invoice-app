<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Issuer\Enums\MailProvider;
use App\Domain\Issuer\MailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuenta de correo para enviar los documentos. La contraseña solo es
 * obligatoria la primera vez: vacía, se conserva la guardada.
 */
final class MailSettingsRequest extends FormRequest
{
    public const int MAX_LENGTH = 200;

    private const int MAX_PORT = 65535;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $custom = $this->input('provider') === MailProvider::Custom->value;
        $needsUsername = $this->provider()?->usesAddressAsUsername() === false;

        return [
            'provider' => ['required', Rule::enum(MailProvider::class)],
            'from_address' => ['required', 'email', 'max:'.self::MAX_LENGTH],
            'username' => [Rule::requiredIf($needsUsername), 'nullable', 'string', 'max:'.self::MAX_LENGTH],
            'password' => [Rule::requiredIf(blank(MailSettings::current()->password)), 'nullable', 'string', 'max:'.self::MAX_LENGTH],
            'host' => [Rule::requiredIf($custom), 'nullable', 'string', 'max:'.self::MAX_LENGTH],
            'port' => [Rule::requiredIf($custom), 'nullable', 'integer', 'between:1,'.self::MAX_PORT],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'provider' => 'servicio de correo',
            'from_address' => 'email',
            'username' => 'usuario',
            'password' => 'contraseña',
            'host' => 'servidor',
            'port' => 'puerto',
        ];
    }

    public function provider(): ?MailProvider
    {
        return MailProvider::tryFrom((string) $this->input('provider'));
    }

    /** Google muestra la contraseña de aplicación en grupos de cuatro: los espacios sobran. */
    protected function prepareForValidation(): void
    {
        $password = $this->input('password');

        if (is_string($password) && $this->provider() === MailProvider::Gmail) {
            $this->merge(['password' => str_replace(' ', '', $password)]);
        }
    }
}
