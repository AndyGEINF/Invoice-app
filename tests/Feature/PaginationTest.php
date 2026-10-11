<?php

declare(strict_types=1);

use App\Domain\Catalog\Product;
use App\Domain\Customers\Customer;
use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;

/** 12 filas: con 10 por página hay dos páginas (10 + 2). */
const PAGINATION_ROWS = 12;

dataset('listados', [
    'facturas' => ['/invoices', 'documents', fn () => Invoice::factory()->count(PAGINATION_ROWS)->create()],
    'presupuestos' => ['/quotes', 'documents', fn () => Quote::factory()->count(PAGINATION_ROWS)->create()],
    'rectificativas' => ['/credit-notes', 'documents', fn () => CreditNote::factory()->count(PAGINATION_ROWS)->create()],
    'clientes' => ['/customers', 'customers', fn () => Customer::factory()->count(PAGINATION_ROWS)->create()],
    'productos' => ['/products', 'products', fn () => Product::factory()->count(PAGINATION_ROWS)->create()],
]);

beforeEach(function () {
    configuredIssuer();
});

it('pagina de 10 en 10 por defecto y la segunda página trae el resto', function (string $url, string $prop, Closure $seed) {
    $seed();

    inertiaGet($url)
        ->assertJsonCount(10, "props.{$prop}.data")
        ->assertJsonPath("props.{$prop}.per_page", 10)
        ->assertJsonPath("props.{$prop}.total", PAGINATION_ROWS)
        ->assertJsonPath("props.{$prop}.last_page", 2);

    inertiaGet("{$url}?page=2")->assertJsonCount(2, "props.{$prop}.data");
})->with('listados');

it('admite 25 y 50 filas por página y conserva el tamaño en los enlaces', function (string $url, string $prop, Closure $seed) {
    $seed();

    inertiaGet("{$url}?per_page=25")
        ->assertJsonCount(PAGINATION_ROWS, "props.{$prop}.data")
        ->assertJsonPath("props.{$prop}.per_page", 25);

    inertiaGet("{$url}?per_page=50")->assertJsonPath("props.{$prop}.per_page", 50);

    $links = inertiaGet("{$url}?per_page=10")->json("props.{$prop}.next_page_url");
    expect($links)->toContain('per_page=10');
})->with('listados');

it('un tamaño que no está entre las opciones vuelve a 10', function (string $url, string $prop, Closure $seed) {
    $seed();

    inertiaGet("{$url}?per_page=7")->assertJsonPath("props.{$prop}.per_page", 10);
    inertiaGet("{$url}?per_page=100000")->assertJsonPath("props.{$prop}.per_page", 10);
    inertiaGet("{$url}?per_page=abc")->assertJsonPath("props.{$prop}.per_page", 10);
})->with('listados');
