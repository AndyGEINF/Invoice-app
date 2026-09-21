<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Exceptions\InvalidTaxId;
use App\Domain\Shared\TaxId;

describe('TaxId español', function () {
    it('acepta identificadores con dígito de control correcto', function (string $value, TaxIdType $type) {
        $taxId = TaxId::of($value);

        expect($taxId->isValid())->toBeTrue()
            ->and($taxId->type)->toBe($type)
            ->and($taxId->isSpanish())->toBeTrue()
            ->and($taxId->isEuVat())->toBeFalse();
    })->with([
        'NIF' => ['12345678Z', TaxIdType::NIF],
        'NIF con ceros a la izquierda' => ['00000010X', TaxIdType::NIF],
        'NIE que empieza por X' => ['X1234567L', TaxIdType::NIE],
        'NIE que empieza por Y' => ['Y0000000Z', TaxIdType::NIE],
        'CIF de sociedad limitada' => ['B12345674', TaxIdType::CIF],
        'CIF con control de letra' => ['P1234567D', TaxIdType::CIF],
    ]);

    it('rechaza identificadores con dígito de control incorrecto', function (string $value) {
        expect(TaxId::of($value)->isValid())->toBeFalse();
    })->with([
        'NIF con letra que no corresponde' => ['12345678A'],
        'CIF con control incorrecto' => ['B12345678'],
        'NIE con letra incorrecta' => ['X1234567A'],
        'letra inicial que no es de CIF' => ['I12345674'],
        'demasiado corto' => ['1234567Z'],
        'sin letra' => ['123456789'],
        'texto' => ['no soy un nif'],
        'vacío' => [''],
    ]);

    it('normaliza espacios, guiones, puntos y minúsculas', function () {
        expect(TaxId::of(' b-12.345.674 ')->value)->toBe('B12345674')
            ->and(TaxId::of('12345678z')->value)->toBe('12345678Z')
            ->and(TaxId::of('12345678z')->isValid())->toBeTrue();
    });
});

describe('TaxId intracomunitario', function () {
    it('reconoce identificadores de la Unión Europea', function (string $value) {
        $taxId = TaxId::of($value);

        expect($taxId->type)->toBe(TaxIdType::VAT_EU)
            ->and($taxId->isValid())->toBeTrue()
            ->and($taxId->isEuVat())->toBeTrue()
            ->and($taxId->isSpanish())->toBeFalse();
    })->with([
        'Alemania' => ['DE123456789'],
        'Francia' => ['FR12345678901'],
        'Portugal' => ['PT123456789'],
        'Italia' => ['IT12345678901'],
        'Grecia usa el prefijo EL' => ['EL123456789'],
    ]);

    it('devuelve el país del identificador', function () {
        expect(TaxId::of('DE123456789')->country)->toBe('DE')
            ->and(TaxId::of('B12345674')->country)->toBe('ES');
    });

    it('trata el prefijo ES como identificador español válido', function () {
        $taxId = TaxId::of('ESB12345674');

        expect($taxId->type)->toBe(TaxIdType::VAT_EU)
            ->and($taxId->isValid())->toBeTrue()
            ->and($taxId->country)->toBe('ES')
            ->and($taxId->isSpanish())->toBeTrue();
    });

    it('rechaza identificadores europeos con formato incorrecto', function (string $value) {
        expect(TaxId::of($value)->isValid())->toBeFalse();
    })->with([
        'Alemania con pocos dígitos' => ['DE1234'],
        'país inexistente' => ['ZZ123456789'],
        'Alemania con letras' => ['DEABCDEFGHI'],
    ]);
});

describe('TaxId genérico', function () {
    it('admite un identificador extranjero cuando se declara como otro', function () {
        $taxId = TaxId::of('CH-123.456', TaxIdType::OTHER);

        expect($taxId->type)->toBe(TaxIdType::OTHER)
            ->and($taxId->isValid())->toBeTrue()
            ->and($taxId->isSpanish())->toBeFalse();
    });

    it('lanza una excepción cuando se exige validez', function () {
        TaxId::valid('12345678A');
    })->throws(InvalidTaxId::class);

    it('devuelve el identificador exigido cuando es correcto', function () {
        expect(TaxId::valid('12345678Z')->value)->toBe('12345678Z');
    });
});
