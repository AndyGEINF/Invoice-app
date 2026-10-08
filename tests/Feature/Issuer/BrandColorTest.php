<?php

declare(strict_types=1);

use App\Domain\Documents\Invoice;
use App\Domain\Issuer\Issuer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const BRAND_RED = '#dc2626';
const BRAND_GREEN = '#16a34a';

beforeEach(function () {
    Storage::fake(config('invoice.storage.logos_disk'));
});

/** Factura emitida con el emisor actual congelado en su snapshot. */
function invoiceIssuedWithCurrentIssuer(): Invoice
{
    return Invoice::factory()->issued()->create(['issuer_snapshot' => Issuer::current()->toSnapshot()]);
}

/** @return array<string, mixed> */
function brandForm(array $overrides = []): array
{
    return [
        'name' => 'Laura Pérez',
        'tax_id' => '12345678Z',
        'address' => ['street' => 'Calle Sol 3', 'city' => 'Lleida', 'postal_code' => '25001'],
        'vat_regime' => 'general',
        'default_irpf_rate' => '15',
        'logo' => UploadedFile::fake()->createWithContent('logo.png', (string) file_get_contents(__DIR__.'/../../Fixtures/logo.png')),
        ...$overrides,
    ];
}

describe('ajustes', function () {
    it('un emisor nuevo usa el azul por defecto', function () {
        expect(Issuer::current()->brand_color)->toBe(Issuer::DEFAULT_BRAND_COLOR);
    });

    it('guarda el color elegido en minúsculas', function () {
        $this->put('/settings/issuer', brandForm(['brand_color' => '#DC2626']))->assertSessionHasNoErrors();

        expect(Issuer::current()->refresh()->brand_color)->toBe(BRAND_RED);
    });

    it('rechaza lo que no sea #RRGGBB', function (string $color) {
        $this->put('/settings/issuer', brandForm(['brand_color' => $color]))->assertSessionHasErrors('brand_color');
    })->with(['rojo', '#f00', 'dc2626', '#dc26266', '#gggggg']);

    it('si no se envía, conserva el que había', function () {
        configuredIssuer(['brand_color' => BRAND_GREEN]);

        $this->put('/settings/issuer', brandForm())->assertSessionHasNoErrors();

        expect(Issuer::current()->refresh()->brand_color)->toBe(BRAND_GREEN);
    });

    it('la pantalla de ajustes recibe el color y las muestras', function () {
        inertiaGet('/settings/issuer')
            ->assertJsonPath('props.settings.brand_color', Issuer::DEFAULT_BRAND_COLOR)
            ->assertJsonPath('props.options.brand_colors', config('invoice.issuer.brand_colors'));
    });
});

describe('documento', function () {
    it('la factura emitida conserva el color congelado aunque el emisor lo cambie después', function () {
        configuredIssuer(['brand_color' => BRAND_RED]);
        $invoice = invoiceIssuedWithCurrentIssuer();

        Issuer::current()->update(['brand_color' => BRAND_GREEN]);

        expect($invoice->refresh()->issuer_snapshot['brand_color'])->toBe(BRAND_RED);
        $this->get("/invoices/{$invoice->id}/preview")
            ->assertOk()
            ->assertSee('--brand: '.BRAND_RED, escape: false)
            ->assertDontSee(BRAND_GREEN);
        inertiaGet("/invoices/{$invoice->id}")->assertJsonPath('props.paper.brandColor', BRAND_RED);
    });

    it('un borrador usa el color actual del emisor', function () {
        configuredIssuer(['brand_color' => BRAND_GREEN]);
        $draft = Invoice::factory()->draft()->create();

        $this->get("/invoices/{$draft->id}/preview")->assertOk()->assertSee('--brand: '.BRAND_GREEN, escape: false);
    });

    it('un snapshot de la versión 1, sin color, se imprime con el azul por defecto', function () {
        configuredIssuer(['brand_color' => BRAND_RED]);
        $snapshot = Issuer::current()->toSnapshot();
        unset($snapshot['brand_color']);
        $invoice = Invoice::factory()->issued()->create(['issuer_snapshot' => [...$snapshot, 'snapshot_version' => 1]]);

        $this->get("/invoices/{$invoice->id}/preview")->assertSee('--brand: '.Issuer::DEFAULT_BRAND_COLOR, escape: false);
    });
});
