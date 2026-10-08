<?php

declare(strict_types=1);

use App\Domain\Issuer\Exceptions\IssuerNotConfigured;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\VatRegime;
use Illuminate\Database\UniqueConstraintViolationException;

/** Datos mínimos para poder emitir. */
function completeIssuerData(array $overrides = []): array
{
    return [
        'name' => 'Andy Moreno',
        'company_name' => 'Demo SL',
        'logo_path' => 'logos/abc123.png',
        'tax_id' => 'B12345674',
        'address' => Address::of('Calle Mayor 1', 'Girona', '17001', 'Girona'),
        'vat_regime' => VatRegime::General,
        'email' => 'facturas@demo.test',
        ...$overrides,
    ];
}

describe('Issuer::current', function () {
    it('crea el emisor vacío la primera vez', function () {
        $issuer = Issuer::current();

        expect(Issuer::query()->count())->toBe(1)
            ->and($issuer->vat_regime)->toBe(VatRegime::General)
            ->and($issuer->default_currency)->toBe('EUR')
            ->and($issuer->isComplete())->toBeFalse();
    });

    it('devuelve siempre la misma fila', function () {
        $primero = Issuer::current();
        $segundo = Issuer::current();

        expect($segundo->id)->toBe($primero->id)
            ->and(Issuer::query()->count())->toBe(1);
    });

    it('la base de datos impide un segundo emisor', function () {
        Issuer::current();

        Issuer::query()->create(['name' => 'Otro']);
    })->throws(UniqueConstraintViolationException::class);
});

describe('datos obligatorios', function () {
    it('lista lo que falta en un emisor vacío', function () {
        expect(Issuer::current()->missing())->toBe(['name', 'logo', 'tax_id', 'address']);
    });

    it('está completo con nombre, logotipo, NIF válido y dirección', function () {
        $issuer = Issuer::current();
        $issuer->fill(completeIssuerData())->save();

        expect($issuer->refresh()->isComplete())->toBeTrue()
            ->and($issuer->missing())->toBe([]);
    });

    it('exige el logotipo', function () {
        $issuer = Issuer::current();
        $issuer->fill(completeIssuerData(['logo_path' => null]))->save();

        expect($issuer->missing())->toBe(['logo']);
    });

    it('rechaza un NIF con dígito de control incorrecto', function () {
        $issuer = Issuer::current();
        $issuer->fill(completeIssuerData(['tax_id' => 'B12345678']))->save();

        expect($issuer->missing())->toBe(['tax_id']);
    });

    it('rechaza una dirección incompleta', function () {
        $issuer = Issuer::current();
        $issuer->fill(completeIssuerData(['address' => Address::of('Calle Mayor 1', 'Girona', '')]))->save();

        expect($issuer->missing())->toBe(['address']);
    });

    it('lanza IssuerNotConfigured con los campos que faltan', function () {
        try {
            Issuer::current()->assertComplete();
            $this->fail('Debía lanzar IssuerNotConfigured');
        } catch (IssuerNotConfigured $e) {
            expect($e->missing)->toBe(['name', 'logo', 'tax_id', 'address']);
        }
    });
});

describe('nombre con el que se factura', function () {
    it('usa la empresa cuando existe', function () {
        $issuer = Issuer::current()->fill(completeIssuerData());

        expect($issuer->legalName())->toBe('Demo SL');
    });

    it('usa el nombre de la persona si no hay empresa', function () {
        $issuer = Issuer::current()->fill(completeIssuerData(['company_name' => '  ']));

        expect($issuer->legalName())->toBe('Andy Moreno');
    });
});

describe('snapshot', function () {
    it('congela nombre, empresa, logotipo y datos fiscales', function () {
        $issuer = Issuer::current();
        $issuer->fill(completeIssuerData())->save();

        $snapshot = $issuer->refresh()->toSnapshot();

        expect($snapshot)->toMatchArray([
            'snapshot_version' => Issuer::SNAPSHOT_VERSION,
            'legal_name' => 'Demo SL',
            'contact_name' => 'Andy Moreno',
            'company_name' => 'Demo SL',
            'logo_path' => 'logos/abc123.png',
            'tax_id' => 'B12345674',
            'vat_regime' => 'general',
            'brand_color' => Issuer::DEFAULT_BRAND_COLOR,
        ])->and($snapshot['address'])->toMatchArray(['city' => 'Girona', 'country' => 'ES']);
    });

    it('no cambia aunque el emisor cambie después', function () {
        $issuer = Issuer::current();
        $issuer->fill(completeIssuerData())->save();
        $snapshot = $issuer->toSnapshot();

        $issuer->update([
            'address' => Address::of('Otra calle 2', 'Barcelona', '08001'),
            'logo_path' => 'logos/nuevo.png',
        ]);

        expect($snapshot['address']['city'])->toBe('Girona')
            ->and($snapshot['logo_path'])->toBe('logos/abc123.png');
    });
});
