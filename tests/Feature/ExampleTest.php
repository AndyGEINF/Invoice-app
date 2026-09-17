<?php

use Inertia\Testing\AssertableInertia as Assert;

it('redirige la raíz al panel', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

it('muestra el panel sin pedir login', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('usa PostgreSQL en los tests', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('invoice_test');
});
