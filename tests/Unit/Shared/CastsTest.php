<?php

declare(strict_types=1);

use App\Domain\Shared\Address;
use App\Domain\Shared\Casts\AddressCast;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Casts\PercentageCast;
use App\Domain\Shared\Casts\QuantityCast;
use App\Domain\Shared\Casts\UnitPriceCast;
use App\Domain\Shared\Currency;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de usar y tirar: los casts se prueban sin tocar la base de datos.
 *
 * Los atributos se fijan en crudo, igual que hace Eloquent al leer una fila.
 */
function castingModel(array $attributes = []): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];

        protected $casts = [
            'total' => MoneyCast::class,
            'unit_price' => UnitPriceCast::class,
            'quantity' => QuantityCast::class,
            'vat_rate' => PercentageCast::class,
            'billing_address' => AddressCast::class,
        ];
    };

    return $model->setRawAttributes($attributes, sync: true);
}

describe('MoneyCast', function () {
    it('lee céntimos de la base de datos como Money', function () {
        $model = castingModel(['total' => 19250]);

        expect($model->total)->toBeInstanceOf(Money::class)
            ->and($model->total->cents)->toBe(19250)
            ->and($model->total->currency)->toBe(Currency::EUR);
    });

    it('usa la moneda del propio modelo', function () {
        $model = castingModel(['currency' => 'USD', 'total' => 100]);

        expect($model->total->currency)->toBe(Currency::USD);
    });

    it('guarda un Money como entero de céntimos', function () {
        $model = castingModel();
        $model->total = Money::fromCents(12345);

        expect($model->getAttributes()['total'])->toBe(12345);
    });

    it('acepta una cadena decimal y la redondea a céntimos', function () {
        $model = castingModel();
        $model->total = '10.005';

        expect($model->getAttributes()['total'])->toBe(1001);
    });

    it('respeta los nulos', function () {
        $model = castingModel(['total' => null]);

        expect($model->total)->toBeNull();
    });
});

describe('UnitPriceCast', function () {
    it('convierte milésimas en UnitPrice', function () {
        $model = castingModel(['unit_price' => 33333]);

        expect($model->unit_price)->toBeInstanceOf(UnitPrice::class)
            ->and($model->unit_price->toDecimalString())->toBe('33.333');
    });

    it('guarda una cadena decimal como milésimas', function () {
        $model = castingModel();
        $model->unit_price = '33.333';

        expect($model->getAttributes()['unit_price'])->toBe(33333);
    });
});

describe('QuantityCast', function () {
    it('convierte la columna numeric en Quantity', function () {
        $model = castingModel(['quantity' => '3.0000']);

        expect($model->quantity)->toBeInstanceOf(Quantity::class)
            ->and($model->quantity->value)->toBe('3.0000');
    });

    it('guarda con cuatro decimales', function () {
        $model = castingModel();
        $model->quantity = Quantity::of('1.5');

        expect($model->getAttributes()['quantity'])->toBe('1.5000');
    });
});

describe('PercentageCast', function () {
    it('convierte la columna numeric en Percentage', function () {
        $model = castingModel(['vat_rate' => '21.00']);

        expect($model->vat_rate)->toBeInstanceOf(Percentage::class)
            ->and($model->vat_rate->value)->toBe('21.00');
    });

    it('guarda con dos decimales', function () {
        $model = castingModel();
        $model->vat_rate = '5.2';

        expect($model->getAttributes()['vat_rate'])->toBe('5.20');
    });
});

describe('AddressCast', function () {
    it('lee el JSON de la base de datos como Address', function () {
        $json = json_encode([
            'street' => 'Calle Mayor 1',
            'city' => 'Girona',
            'postal_code' => '17001',
            'province' => 'Girona',
            'country' => 'ES',
        ]);

        $model = castingModel(['billing_address' => $json]);

        expect($model->billing_address)->toBeInstanceOf(Address::class)
            ->and($model->billing_address->city)->toBe('Girona')
            ->and($model->billing_address->isComplete())->toBeTrue();
    });

    it('guarda una Address como JSON', function () {
        $model = castingModel();
        $model->billing_address = Address::of('Calle Mayor 1', 'Girona', '17001');

        expect(json_decode($model->getAttributes()['billing_address'], true))
            ->toMatchArray(['street' => 'Calle Mayor 1', 'country' => 'ES']);
    });

    it('acepta un array plano', function () {
        $model = castingModel();
        $model->billing_address = ['street' => 'Calle Mayor 1', 'city' => 'Girona', 'postal_code' => '17001'];

        expect($model->billing_address->city)->toBe('Girona');
    });
});
