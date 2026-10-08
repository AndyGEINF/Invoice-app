<?php

declare(strict_types=1);

use App\Domain\Customers\Customer;
use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Enums\TaxIdType;
use App\Http\Queries\CatalogIndexQuery;

/** @return array<string, mixed> */
function customerForm(array $overrides = []): array
{
    return [
        'kind' => 'business',
        'legal_name' => 'Talleres Pérez SL',
        'tax_id' => 'B12345674',
        'billing_address' => ['street' => 'Calle Sol 3', 'city' => 'Lleida', 'postal_code' => '25001'],
        'payment_terms_days' => 30,
        'contacts' => [
            ['name' => 'Ana', 'email' => 'ana@talleres.test'],
            ['name' => 'Luis', 'email' => 'luis@talleres.test', 'is_default' => true],
        ],
        ...$overrides,
    ];
}

describe('alta', function () {
    it('crea el cliente con sus contactos y lleva a su ficha', function () {
        $response = $this->post('/customers', customerForm(['tax_id' => 'b-1234567-4']))->assertSessionHasNoErrors();

        $customer = Customer::query()->sole();
        $response->assertRedirect("/customers/{$customer->id}");

        expect($customer->kind)->toBe(CustomerKind::Business)
            ->and($customer->tax_id)->toBe('B12345674')
            ->and($customer->tax_id_type)->toBe(TaxIdType::CIF)
            ->and($customer->contacts()->count())->toBe(2)
            ->and($customer->defaultContact()->name)->toBe('Luis');
    });

    it('una empresa sin NIF no se guarda', function () {
        $this->post('/customers', customerForm(['tax_id' => '']))->assertSessionHasErrors('tax_id');

        expect(Customer::query()->count())->toBe(0);
    });

    it('un NIF con la letra de control mal no se guarda', function () {
        $this->post('/customers', customerForm(['tax_id' => 'B12345678']))->assertSessionHasErrors('tax_id');
    });

    it('responde 422 a una petición JSON con errores', function () {
        $this->postJson('/customers', customerForm(['tax_id' => '']))->assertUnprocessable()->assertJsonValidationErrors('tax_id');
    });

    it('un particular puede no tener NIF ni dirección', function () {
        $this->post('/customers', customerForm(['kind' => 'individual', 'legal_name' => 'Laura Martín', 'tax_id' => '', 'billing_address' => []]))
            ->assertSessionHasNoErrors();

        expect(Customer::query()->sole()->tax_id)->toBeNull();
    });

    it('a un particular nunca se le aplica retención', function () {
        $this->post('/customers', customerForm(['kind' => 'individual', 'tax_id' => '12345678Z', 'irpf_applies' => true]));

        expect(Customer::query()->sole()->appliesIrpf())->toBeFalse();
    });
});

describe('NIF duplicado', function () {
    it('avisa con duplicate_warning y no guarda', function () {
        $existing = Customer::factory()->create(['tax_id' => 'B12345674', 'tax_id_type' => TaxIdType::CIF]);

        $this->from('/customers/create')
            ->post('/customers', customerForm(['legal_name' => 'Talleres Pérez Norte']))
            ->assertRedirect('/customers/create')
            ->assertInertiaFlash('duplicate_warning', ['id' => $existing->id, 'legal_name' => $existing->legal_name, 'tax_id' => 'B12345674']);

        expect(Customer::query()->count())->toBe(1);
    });

    it('con ?force=1 lo crea igualmente', function () {
        Customer::factory()->create(['tax_id' => 'B12345674', 'tax_id_type' => TaxIdType::CIF]);

        $this->post('/customers?force=1', customerForm(['legal_name' => 'Talleres Pérez Norte']))->assertSessionHasNoErrors();

        expect(Customer::query()->where('tax_id', 'B12345674')->count())->toBe(2);
    });

    it('editar un cliente sin cambiar su NIF no se avisa a sí mismo', function () {
        $customer = Customer::factory()->create(['tax_id' => 'B12345674', 'tax_id_type' => TaxIdType::CIF]);

        $this->put("/customers/{$customer->id}", customerForm(['legal_name' => 'Nuevo nombre']))
            ->assertRedirect("/customers/{$customer->id}")
            ->assertInertiaFlashMissing('duplicate_warning');

        expect($customer->refresh()->legal_name)->toBe('Nuevo nombre');
    });
});

describe('edición', function () {
    it('sustituye los contactos por los del formulario', function () {
        $customer = Customer::factory()->hasContacts(3)->create(['tax_id' => 'B12345674']);

        $this->put("/customers/{$customer->id}", customerForm(['contacts' => [['name' => 'Solo', 'email' => 'solo@talleres.test']]]));

        expect($customer->contacts()->pluck('name')->all())->toBe(['Solo'])
            ->and($customer->defaultContact()->name)->toBe('Solo');
    });

    it('cambiar de NIF anula la validación VIES anterior', function () {
        $customer = Customer::factory()->create(['tax_id' => 'B12345674', 'vies_validated_at' => now()]);

        $this->put("/customers/{$customer->id}", customerForm(['tax_id' => 'A58818501']));

        expect($customer->refresh()->vies_validated_at)->toBeNull();
    });
});

describe('archivo', function () {
    it('archiva y restaura', function () {
        $customer = Customer::factory()->create();

        $this->post("/customers/{$customer->id}/archive")->assertRedirect("/customers/{$customer->id}");
        expect($customer->refresh()->isArchived())->toBeTrue();

        $this->post("/customers/{$customer->id}/restore");
        expect($customer->refresh()->isArchived())->toBeFalse();
    });

    it('un cliente con facturas emitidas se archiva y conserva sus documentos', function () {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->issued()->for($customer)->create();

        $this->post("/customers/{$customer->id}/archive");

        expect(Customer::query()->find($customer->id))->not->toBeNull()
            ->and($invoice->refresh()->customer_id)->toBe($customer->id);
    });

    it('no hay forma de borrar un cliente', function () {
        $customer = Customer::factory()->create();

        // No existe la ruta: según el orden de las rutas responde 404 o 405, nunca éxito.
        expect($this->delete("/customers/{$customer->id}")->status())->toBeIn([404, 405]);
        expect(Customer::query()->find($customer->id))->not->toBeNull();
    });

    it('el listado separa activos y archivados', function () {
        Customer::factory()->count(2)->create();
        Customer::factory()->create(['archived_at' => now()]);

        inertiaGet('/customers')
            ->assertJsonPath('component', 'customers/index')
            ->assertJsonCount(2, 'props.customers.data')
            ->assertJsonPath('props.counts', ['active' => 2, 'archived' => 1]);
        inertiaGet('/customers?archived=1')->assertJsonCount(1, 'props.customers.data');
    });
});

describe('búsqueda', function () {
    it('encuentra por nombre, nombre comercial o NIF sin distinguir mayúsculas', function () {
        Customer::factory()->create(['legal_name' => 'Ferretería La Esquina SL', 'tax_id' => 'B12345674']);
        Customer::factory()->create(['legal_name' => 'Otra SL', 'trade_name' => 'Bar Pepe', 'tax_id' => 'A58818501']);

        expect($this->getJson('/customers/search?q=ESQUINA')->json('*.legal_name'))->toBe(['Ferretería La Esquina SL'])
            ->and($this->getJson('/customers/search?q=pepe')->json('*.legal_name'))->toBe(['Otra SL'])
            ->and($this->getJson('/customers/search?q=A5881')->json('*.legal_name'))->toBe(['Otra SL']);
    });

    it('devuelve como mucho 20 y nunca archivados', function () {
        Customer::factory()->count(CatalogIndexQuery::SEARCH_LIMIT + 5)->create();
        Customer::factory()->create(['legal_name' => 'Archivado SL', 'archived_at' => now()]);

        $results = $this->getJson('/customers/search')->assertOk()->json();

        expect($results)->toHaveCount(CatalogIndexQuery::SEARCH_LIMIT)
            ->and(array_column($results, 'legal_name'))->not->toContain('Archivado SL')
            ->and(array_keys($results[0]))->toContain('id', 'legal_name', 'tax_id', 'email', 'irpf_applies', 'surcharge_applies');
    });

    it('los comodines de LIKE se buscan literalmente', function () {
        Customer::factory()->create(['legal_name' => 'Cien por cien SL']);

        expect($this->getJson('/customers/search?q=%25')->json())->toBe([]);
    });
});
