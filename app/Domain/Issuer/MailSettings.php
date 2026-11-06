<?php

declare(strict_types=1);

namespace App\Domain\Issuer;

use App\Domain\Issuer\Enums\MailProvider;
use App\Domain\Issuer\Enums\MailTestStatus;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta de correo que el usuario conecta en Ajustes para enviar los
 * documentos. Sin ella se usa la configuración del servidor (`MAIL_*`), que en
 * local escribe los correos en el log en lugar de enviarlos.
 *
 * La contraseña va cifrada y nunca sale hacia la interfaz.
 *
 * @property string $id
 * @property MailProvider|null $provider
 * @property string|null $host
 * @property int|null $port
 * @property string|null $username
 * @property string|null $password
 * @property string|null $from_address
 * @property MailTestStatus|null $test_status
 * @property string|null $test_error
 * @property CarbonImmutable|null $tested_at
 */
final class MailSettings extends Model
{
    use HasUuidPrimaryKey;

    /** Puerto de SMTP con TLS directo; el resto usa STARTTLS. */
    private const int SMTPS_PORT = 465;

    /** Segundos de espera al servidor antes de dar el envío por fallido. */
    private const int TIMEOUT_SECONDS = 30;

    protected $table = 'mail_settings';

    protected $fillable = ['provider', 'host', 'port', 'username', 'password', 'from_address'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
            'provider' => MailProvider::class,
            'port' => 'integer',
            'password' => 'encrypted',
            'test_status' => MailTestStatus::class,
            'tested_at' => 'immutable_datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['singleton' => true]);
    }

    public function isConfigured(): bool
    {
        return $this->provider !== null
            && filled($this->host)
            && $this->port !== null
            && filled($this->username)
            && filled($this->password)
            && filled($this->from_address);
    }

    /**
     * Configuración de un mailer SMTP de Laravel con esta cuenta.
     *
     * @return array<string, mixed>
     */
    public function transportConfig(): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $this->port === self::SMTPS_PORT ? 'smtps' : null,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => self::TIMEOUT_SECONDS,
        ];
    }
}
