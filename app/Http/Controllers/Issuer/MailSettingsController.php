<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issuer;

use App\Application\Issuer\UpdateMailSettings;
use App\Domain\Issuer\Enums\MailProvider;
use App\Domain\Issuer\MailSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\MailSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes > Correo: la cuenta desde la que salen los documentos. La contraseña
 * nunca se envía a la interfaz; solo si hay una guardada.
 */
final class MailSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = MailSettings::current();

        return Inertia::render('settings/mail', [
            'mail' => [
                'provider' => $settings->provider?->value,
                'from_address' => $settings->from_address,
                'username' => $settings->provider?->usesAddressAsUsername() === false ? $settings->username : null,
                'host' => $settings->provider === MailProvider::Custom ? $settings->host : null,
                'port' => $settings->provider === MailProvider::Custom ? $settings->port : null,
                'has_password' => filled($settings->password),
                'configured' => $settings->isConfigured(),
                'test_status' => $settings->test_status?->value,
                'test_error' => $settings->test_error,
                'tested_at' => $settings->tested_at?->toIso8601String(),
            ],
            'providers' => array_map(
                static fn (MailProvider $provider): array => ['value' => $provider->value, 'label' => $provider->label()],
                MailProvider::cases(),
            ),
        ]);
    }

    public function update(MailSettingsRequest $request, UpdateMailSettings $update): RedirectResponse
    {
        $data = $request->validated();

        $update(
            $request->provider() ?? MailProvider::Custom,
            (string) $data['from_address'],
            $data['username'] ?? null,
            $data['password'] ?? null,
            $data['host'] ?? null,
            isset($data['port']) ? (int) $data['port'] : null,
        );

        Inertia::flash('success', 'Datos guardados. Te estamos enviando un correo de prueba.');

        return to_route('settings.mail');
    }

    /** Desconectar: se borran los datos y los envíos vuelven a la configuración del servidor. */
    public function destroy(): RedirectResponse
    {
        MailSettings::current()->delete();

        Inertia::flash('success', 'Correo desconectado.');

        return to_route('settings.mail');
    }
}
