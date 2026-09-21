<?php

declare(strict_types=1);

use App\Domain\Shared\Address;

describe('Address', function () {
    it('se crea y se convierte a array con las claves de la base de datos', function () {
        $address = Address::of('Calle Mayor 1', 'Girona', '17001', 'Girona');

        expect($address->toArray())->toBe([
            'street' => 'Calle Mayor 1',
            'city' => 'Girona',
            'postal_code' => '17001',
            'province' => 'Girona',
            'country' => 'ES',
        ]);
    });

    it('se reconstruye desde un array', function () {
        $data = [
            'street' => 'Rua Augusta 10',
            'city' => 'Lisboa',
            'postal_code' => '1100-053',
            'province' => '',
            'country' => 'pt',
        ];

        $address = Address::fromArray($data);

        expect($address->country)->toBe('PT')
            ->and($address->isSpanish())->toBeFalse()
            ->and($address->toArray()['street'])->toBe('Rua Augusta 10');
    });

    it('tolera claves ausentes', function () {
        $address = Address::fromArray(['street' => 'Calle Mayor 1']);

        expect($address->city)->toBe('')
            ->and($address->country)->toBe('ES')
            ->and($address->isComplete())->toBeFalse();
    });

    it('está completa cuando sirve para facturar', function () {
        $completa = Address::of('Calle Mayor 1', 'Girona', '17001');
        $sinCodigoPostal = Address::of('Calle Mayor 1', 'Girona', '');

        expect($completa->isComplete())->toBeTrue()
            ->and($sinCodigoPostal->isComplete())->toBeFalse()
            ->and(Address::empty()->isComplete())->toBeFalse();
    });

    it('quita espacios sobrantes', function () {
        $address = Address::of('  Calle Mayor 1  ', ' Girona ', ' 17001 ', ' Girona ', ' es ');

        expect($address->street)->toBe('Calle Mayor 1')
            ->and($address->postalCode)->toBe('17001')
            ->and($address->country)->toBe('ES');
    });

    it('se imprime en una línea para el PDF', function () {
        expect(Address::of('Calle Mayor 1', 'Girona', '17001', 'Girona')->singleLine())
            ->toBe('Calle Mayor 1, 17001 Girona, ES')
            ->and(Address::of('Calle Mayor 1', 'Salt', '17190', 'Girona')->singleLine())
            ->toBe('Calle Mayor 1, 17190 Salt (Girona), ES');
    });

    it('compara por contenido', function () {
        expect(Address::of('Calle Mayor 1', 'Girona', '17001')->equals(Address::of('Calle Mayor 1', 'Girona', '17001')))->toBeTrue()
            ->and(Address::of('Calle Mayor 1', 'Girona', '17001')->equals(Address::of('Calle Mayor 2', 'Girona', '17001')))->toBeFalse();
    });
});
