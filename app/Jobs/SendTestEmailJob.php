<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Issuer\Enums\MailTestStatus;
use App\Domain\Issuer\MailSettings;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Mail\ConfiguredMailer;
use App\Infrastructure\Mail\MailErrorMessage;
use App\Mail\TestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Manda el correo de prueba a la cuenta recién conectada y apunta el resultado.
 * Sin reintentos: el usuario está esperando y un fallo suele ser un dato mal
 * escrito. Idempotente: solo actúa si hay una prueba pendiente.
 */
final class SendTestEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(ConfiguredMailer $mailer, Clock $clock): void
    {
        $settings = MailSettings::current();

        if ($settings->test_status !== MailTestStatus::Pending) {
            return;
        }

        try {
            $mailer->mailer()->to((string) $settings->from_address)->send(new TestMail($mailer->fromAddress()));
            $result = ['test_status' => MailTestStatus::Ok, 'test_error' => null];
        } catch (Throwable $exception) {
            $result = ['test_status' => MailTestStatus::Failed, 'test_error' => MailErrorMessage::for($exception)];
        }

        $settings->forceFill([...$result, 'tested_at' => $clock->now()])->save();
    }
}
