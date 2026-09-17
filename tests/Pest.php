<?php

use App\Domain\Issuer\Issuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests Unit no tocan la base de datos. Los de Feature y Concurrency usan
| la aplicación completa contra PostgreSQL (base invoice_test) y se limpian
| con RefreshDatabase.
|
*/

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->group('concurrency')
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Devuelve el emisor único con todos los datos obligatorios para emitir
 * (nombre, empresa, logotipo, NIF, dirección y régimen de IVA).
 */
function configuredIssuer(array $overrides = []): Issuer
{
    $issuer = Issuer::current();
    $issuer->fill(Issuer::factory()->complete()->raw($overrides));
    $issuer->save();

    return $issuer->refresh();
}
