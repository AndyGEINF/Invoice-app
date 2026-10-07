<?php

declare(strict_types=1);

use App\Domain\Issuer\Issuer;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const LOGO_FIXTURE = __DIR__.'/../../Fixtures/logo.png';

/** Por encima del máximo de 2 MB de config/invoice.php. */
const OVERSIZED_LOGO_KB = 3072;

beforeEach(function () {
    Storage::fake(config('invoice.storage.logos_disk'));
});

/** Petición Inertia: devuelve las props de la página en JSON. */
function inertiaGet(string $url): TestResponse
{
    return test()->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) (new HandleInertiaRequests)->version(request()),
    ])->get($url);
}

function logoUpload(string $suffix = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.png', file_get_contents(LOGO_FIXTURE).$suffix);
}

/** @return array<string, mixed> */
function issuerForm(array $overrides = []): array
{
    return [
        'name' => 'Laura Pérez',
        'company_name' => '',
        'tax_id' => '12345678Z',
        'address' => ['street' => 'Calle Sol 3', 'city' => 'Lleida', 'postal_code' => '25001', 'province' => 'Lleida'],
        'vat_regime' => 'general',
        'default_irpf_rate' => '15',
        'logo' => logoUpload(),
        ...$overrides,
    ];
}

describe('pantalla de ajustes', function () {
    it('en una instalación vacía indica todo lo que falta', function () {
        inertiaGet('/settings/issuer')
            ->assertOk()
            ->assertJsonPath('component', 'settings/issuer')
            ->assertJsonPath('props.issuer.missing', [Issuer::MISSING_NAME, Issuer::MISSING_LOGO, Issuer::MISSING_TAX_ID, Issuer::MISSING_ADDRESS]);
    });
});

describe('validación', function () {
    it('exige el nombre', function () {
        $this->put('/settings/issuer', issuerForm(['name' => '']))->assertSessionHasErrors('name');
    });

    it('exige el logotipo si aún no hay ninguno', function () {
        $form = issuerForm();
        unset($form['logo']);

        $this->put('/settings/issuer', $form)->assertSessionHasErrors('logo');
    });

    it('rechaza un logotipo de más de 2 MB', function () {
        $this->put('/settings/issuer', issuerForm(['logo' => UploadedFile::fake()->create('logo.png', OVERSIZED_LOGO_KB, 'image/png')]))
            ->assertSessionHasErrors('logo');
    });

    it('rechaza un NIF con la letra de control mal', function () {
        $this->put('/settings/issuer', issuerForm(['tax_id' => 'B12345678']))->assertSessionHasErrors('tax_id');
    });

    it('exige la dirección completa salvo la provincia', function () {
        $this->put('/settings/issuer', issuerForm(['address' => ['street' => '', 'city' => 'Lleida', 'postal_code' => '']]))
            ->assertSessionHasErrors(['address.street', 'address.postal_code'])
            ->assertSessionDoesntHaveErrors('address.province');
    });
});

describe('guardado', function () {
    it('guarda el logotipo como {sha256}.png y deja el emisor completo', function () {
        $this->put('/settings/issuer', issuerForm())->assertRedirect('/settings/issuer')->assertSessionHasNoErrors();

        $issuer = Issuer::current()->refresh();
        $expectedPath = hash('sha256', (string) file_get_contents(LOGO_FIXTURE)).'.png';

        expect($issuer->logo_path)->toBe($expectedPath)
            ->and($issuer->isComplete())->toBeTrue()
            ->and($issuer->tax_id)->toBe('12345678Z');
        Storage::disk(config('invoice.storage.logos_disk'))->assertExists($expectedPath);
    });

    it('cambiar el logotipo crea otro fichero y conserva el anterior', function () {
        $this->put('/settings/issuer', issuerForm());
        $first = Issuer::current()->refresh()->logo_path;

        $this->put('/settings/issuer', issuerForm(['logo' => logoUpload('otro')]))->assertSessionHasNoErrors();
        $second = Issuer::current()->refresh()->logo_path;

        expect($second)->not->toBe($first);
        Storage::disk(config('invoice.storage.logos_disk'))->assertExists([$first, $second]);
    });

    it('con logotipo guardado no hace falta volver a subirlo', function () {
        $this->put('/settings/issuer', issuerForm());
        $form = issuerForm(['name' => 'Laura Pérez Gil']);
        unset($form['logo']);

        $this->put('/settings/issuer', $form)->assertSessionHasNoErrors();

        expect(Issuer::current()->refresh()->name)->toBe('Laura Pérez Gil');
    });

    it('factura a nombre de la persona si no hay empresa, y de la empresa si la hay', function () {
        $this->put('/settings/issuer', issuerForm());
        expect(Issuer::current()->refresh()->legalName())->toBe('Laura Pérez');

        $this->put('/settings/issuer', issuerForm(['company_name' => 'Estudio Pérez SL']));
        expect(Issuer::current()->refresh()->legalName())->toBe('Estudio Pérez SL');
    });

    it('tras guardar, el aviso de datos incompletos desaparece de todas las páginas', function () {
        inertiaGet('/dashboard')->assertJsonPath('props.issuer.isComplete', false);

        $this->put('/settings/issuer', issuerForm());

        inertiaGet('/dashboard')
            ->assertJsonPath('props.issuer.isComplete', true)
            ->assertJsonPath('props.issuer.missing', []);
    });
});
