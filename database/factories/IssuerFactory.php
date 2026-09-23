<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Enums\VatRegime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * El emisor es una fila única: usa {@see Issuer::current()} y rellénalo con
 * `Issuer::factory()->complete()->raw()` en vez de crear filas nuevas.
 *
 * @extends Factory<Issuer>
 */
final class IssuerFactory extends Factory
{
    protected $model = Issuer::class;

    /** Logotipo de prueba que usan los tests y el seeder de demostración. */
    public const string TEST_LOGO_PATH = 'logos/test-logo.png';

    public function definition(): array
    {
        return [
            'vat_regime' => VatRegime::General,
            'default_irpf_rate' => '0.00',
            'default_currency' => 'EUR',
        ];
    }

    /** Emisor con todo lo necesario para emitir: nombre, empresa, logotipo y datos fiscales. */
    public function complete(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Andy Moreno',
            'company_name' => 'Demo SL',
            'logo_path' => self::TEST_LOGO_PATH,
            'tax_id' => 'B12345674',
            'tax_id_type' => TaxIdType::CIF,
            'address' => Address::of('Calle Mayor 1', 'Girona', '17001', 'Girona'),
            'email' => 'facturas@demo.test',
            'phone' => '972000000',
            'invoice_footer' => 'Inscrita en el Registro Mercantil de Girona.',
        ]);
    }

    /** Autónomo que factura a su nombre, sin empresa. */
    public function selfEmployed(): self
    {
        return $this->complete()->state(fn (): array => [
            'company_name' => null,
            'tax_id' => '12345678Z',
            'tax_id_type' => TaxIdType::NIF,
            'default_irpf_rate' => '15.00',
        ]);
    }
}
