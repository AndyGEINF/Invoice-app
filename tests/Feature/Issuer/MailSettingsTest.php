<?php

declare(strict_types=1);

use App\Domain\Documents\Invoice;
use App\Domain\Issuer\Enums\MailProvider;
use App\Domain\Issuer\Enums\MailTestStatus;
use App\Domain\Issuer\MailSettings;
use App\Jobs\SendTestEmailJob;
use App\Mail\TestMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

const GMAIL_ADDRESS = 'laura@gmail.com';

/** Google la muestra así, en grupos de cuatro. */
const GMAIL_APP_PASSWORD = 'abcd efgh ijkl mnop';

/** @return array<string, mixed> */
function gmailForm(array $overrides = []): array
{
    return ['provider' => 'gmail', 'from_address' => GMAIL_ADDRESS, 'password' => GMAIL_APP_PASSWORD, ...$overrides];
}

describe('pantalla', function () {
    it('sin cuenta indica que no está conectado', function () {
        inertiaGet('/settings/mail')
            ->assertOk()
            ->assertJsonPath('component', 'settings/mail')
            ->assertJsonPath('props.mail.configured', false)
            ->assertJsonPath('props.mail.has_password', false);
    });

    it('nunca envía la contraseña a la interfaz', function () {
        Queue::fake();
        $this->put('/settings/mail', gmailForm());

        $response = inertiaGet('/settings/mail')->assertJsonPath('props.mail.has_password', true);

        expect($response->getContent())->not->toContain('abcdefghijklmnop');
    });
});

describe('guardar', function () {
    it('Gmail: pone su servidor, usa el email como usuario, quita los espacios y cifra la contraseña', function () {
        Queue::fake();

        $this->put('/settings/mail', gmailForm())->assertRedirect('/settings/mail')->assertSessionHasNoErrors();

        $settings = MailSettings::current();
        expect($settings->provider)->toBe(MailProvider::Gmail)
            ->and($settings->host)->toBe('smtp.gmail.com')
            ->and($settings->port)->toBe(587)
            ->and($settings->username)->toBe(GMAIL_ADDRESS)
            ->and($settings->password)->toBe('abcdefghijklmnop')
            ->and(DB::table('mail_settings')->value('password'))->not->toContain('abcdefghijklmnop')
            ->and($settings->isConfigured())->toBeTrue();
    });

    it('encola un correo de prueba y queda pendiente', function () {
        Queue::fake();

        $this->put('/settings/mail', gmailForm());

        Queue::assertPushed(SendTestEmailJob::class);
        expect(MailSettings::current()->test_status)->toBe(MailTestStatus::Pending);
    });

    it('sin contraseña nueva conserva la guardada', function () {
        Queue::fake();
        $this->put('/settings/mail', gmailForm());

        $this->put('/settings/mail', gmailForm(['password' => '']))->assertSessionHasNoErrors();

        expect(MailSettings::current()->password)->toBe('abcdefghijklmnop');
    });

    it('la primera vez la contraseña es obligatoria', function () {
        $this->put('/settings/mail', gmailForm(['password' => '']))->assertSessionHasErrors('password');
    });

    it('otro servidor exige servidor, puerto y usuario', function () {
        $this->put('/settings/mail', gmailForm(['provider' => 'custom']))->assertSessionHasErrors(['host', 'port', 'username']);
    });

    it('rechaza un email mal escrito', function () {
        $this->put('/settings/mail', gmailForm(['from_address' => 'laura@']))->assertSessionHasErrors('from_address');
    });

    it('desconectar borra los datos', function () {
        Queue::fake();
        $this->put('/settings/mail', gmailForm());

        $this->delete('/settings/mail')->assertRedirect('/settings/mail');

        expect(MailSettings::current()->isConfigured())->toBeFalse();
    });
});

describe('correo de prueba', function () {
    it('se manda a la propia cuenta, desde ella, y queda como correcto', function () {
        Mail::fake();

        $this->put('/settings/mail', gmailForm());

        Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo(GMAIL_ADDRESS) && $mail->hasFrom(GMAIL_ADDRESS) && $mail->mailer === 'issuer');
        expect(MailSettings::current()->test_status)->toBe(MailTestStatus::Ok);
    });

    it('si el servidor no responde queda el error explicado', function () {
        $this->put('/settings/mail', gmailForm(['provider' => 'custom', 'username' => GMAIL_ADDRESS, 'host' => '127.0.0.1', 'port' => 1]));

        $settings = MailSettings::current();
        expect($settings->test_status)->toBe(MailTestStatus::Failed)
            ->and($settings->test_error)->toStartWith('No se ha podido conectar con el servidor de correo.');
    });
});

describe('envío de documentos', function () {
    it('el diálogo avisa si el correo no está conectado', function () {
        configuredIssuer();
        $invoice = Invoice::factory()->issued()->create();

        inertiaGet("/invoices/{$invoice->id}")->assertJsonPath('props.sendDefaults.mail_connected', false);

        Queue::fake();
        $this->put('/settings/mail', gmailForm());

        inertiaGet("/invoices/{$invoice->id}")->assertJsonPath('props.sendDefaults.mail_connected', true);
    });
});
