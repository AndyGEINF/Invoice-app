<?php

declare(strict_types=1);

namespace App\Application\Issuer;

use App\Domain\Issuer\Enums\MailProvider;
use App\Domain\Issuer\Enums\MailTestStatus;
use App\Domain\Issuer\MailSettings;
use App\Jobs\SendTestEmailJob;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la cuenta de correo y encola un correo de prueba a ella misma. Gmail y
 * Brevo llevan su servidor ya puesto; sin contraseña nueva se conserva la que
 * había.
 */
final readonly class UpdateMailSettings
{
    public function __invoke(MailProvider $provider, string $fromAddress, ?string $username, ?string $password, ?string $host, ?int $port): MailSettings
    {
        return DB::transaction(function () use ($provider, $fromAddress, $username, $password, $host, $port): MailSettings {
            $settings = MailSettings::current();

            $settings->fill([
                'provider' => $provider,
                'host' => $provider->host() ?? $host,
                'port' => $provider->port() ?? $port,
                'username' => $provider->usesAddressAsUsername() ? $fromAddress : $username,
                'from_address' => $fromAddress,
            ]);

            if (filled($password)) {
                $settings->password = $password;
            }

            $settings->forceFill(['test_status' => MailTestStatus::Pending, 'test_error' => null])->save();

            SendTestEmailJob::dispatch();

            return $settings;
        });
    }
}
