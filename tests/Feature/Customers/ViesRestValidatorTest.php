<?php

declare(strict_types=1);

use App\Domain\Shared\TaxId;
use App\Infrastructure\Vies\ViesRestValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

const VIES_ENDPOINT = 'https://vies.test/rest-api';

function viesValidator(): ViesRestValidator
{
    return app(ViesRestValidator::class, ['endpoint' => VIES_ENDPOINT, 'timeoutSeconds' => 5]);
}

it('consulta /ms/{país}/vat/{número} y da por válido un número confirmado, con su nombre', function () {
    // Respuesta real de VIES (recortada).
    Http::fake([VIES_ENDPOINT.'/ms/IE/vat/6388047V' => Http::response(['isValid' => true, 'userError' => 'VALID', 'name' => 'GOOGLE IRELAND LIMITED'])]);

    $result = viesValidator()->validate(TaxId::of('IE6388047V'));

    expect($result->isConfirmed())->toBeTrue()->and($result->name)->toBe('GOOGLE IRELAND LIMITED');
    Http::assertSent(fn ($request) => $request->url() === VIES_ENDPOINT.'/ms/IE/vat/6388047V');
});

it('"---" como nombre es que el país no lo comparte', function () {
    Http::fake(['*' => Http::response(['isValid' => true, 'userError' => 'VALID', 'name' => '---'])]);

    expect(viesValidator()->validate(TaxId::of('PT123456789'))->name)->toBeNull();
});

it('INVALID es un número que no existe', function () {
    Http::fake(['*' => Http::response(['isValid' => false, 'userError' => 'INVALID'])]);

    $result = viesValidator()->validate(TaxId::of('DE000000000'));

    expect($result->available)->toBeTrue()->and($result->valid)->toBeFalse();
});

it('cualquier fallo del servicio es "no disponible", nunca "no válido"', function (Closure $fake) {
    $fake();

    $result = viesValidator()->validate(TaxId::of('FR12345678901'));

    expect($result->available)->toBeFalse()->and($result->isConfirmed())->toBeFalse();
})->with([
    'estado miembro caído' => fn () => Http::fake(['*' => Http::response(['isValid' => false, 'userError' => 'MS_UNAVAILABLE'])]),
    'error 500' => fn () => Http::fake(['*' => Http::response('Internal error', 500)]),
    'respuesta sin isValid' => fn () => Http::fake(['*' => Http::response(['foo' => 'bar'])]),
    'sin conexión' => fn () => Http::fake(fn () => throw new ConnectionException('timeout')),
]);

it('un NIF español no se consulta', function () {
    Http::fake();

    expect(viesValidator()->validate(TaxId::of('B12345674'))->valid)->toBeFalse();
    Http::assertNothingSent();
});
