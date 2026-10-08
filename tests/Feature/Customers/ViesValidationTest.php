<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Domain\Customers\Contracts\VatNumberValidator;
use App\Domain\Customers\Customer;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Time\FrozenClock;
use App\Infrastructure\Vies\FakeVatNumberValidator;
use App\Jobs\ValidateVatNumberJob;
use Illuminate\Support\Facades\Queue;

const EU_VAT_NUMBER = 'DE123456789';

beforeEach(function () {
    app()->instance(Clock::class, new FrozenClock('2026-10-08 10:00:00'));
});

/** El VIES de mentira que usará el job, ya registrado en el contenedor. */
function fakeVies(FakeVatNumberValidator $validator): FakeVatNumberValidator
{
    app()->instance(VatNumberValidator::class, $validator);

    return $validator;
}

/** @return array<string, mixed> */
function euCustomerForm(array $overrides = []): array
{
    return [
        'kind' => 'business',
        'legal_name' => 'Muster GmbH',
        'tax_id' => EU_VAT_NUMBER,
        'billing_address' => ['street' => 'Hauptstr. 1', 'city' => 'Berlin', 'postal_code' => '10115', 'country' => 'DE'],
        'payment_terms_days' => 30,
        ...$overrides,
    ];
}

describe('encolado', function () {
    it('guardar un cliente con IVA intracomunitario encola la validación', function () {
        Queue::fake();

        $this->post('/customers', euCustomerForm())->assertSessionHasNoErrors();

        $customer = Customer::query()->sole();
        Queue::assertPushed(ValidateVatNumberJob::class, fn (ValidateVatNumberJob $job): bool => $job->customerId === $customer->id && ! $job->force);
    });

    it('un cliente con NIF español no encola nada', function () {
        Queue::fake();

        $this->post('/customers', euCustomerForm(['tax_id' => 'B12345674', 'billing_address' => ['street' => 'Sol 3', 'city' => 'Lleida', 'postal_code' => '25001']]));

        Queue::assertNotPushed(ValidateVatNumberJob::class);
    });

    it('"Comprobar ahora" encola la validación forzada', function () {
        Queue::fake();
        $customer = Customer::factory()->create(['tax_id' => EU_VAT_NUMBER, 'tax_id_type' => 'VAT_EU']);

        $this->post("/customers/{$customer->id}/validate-vat")->assertRedirect("/customers/{$customer->id}");

        Queue::assertPushed(ValidateVatNumberJob::class, fn (ValidateVatNumberJob $job): bool => $job->force);
    });

    it('"Comprobar ahora" no hace nada con un NIF español', function () {
        Queue::fake();
        $customer = Customer::factory()->create(['tax_id' => 'B12345674', 'tax_id_type' => 'CIF']);

        $this->post("/customers/{$customer->id}/validate-vat")->assertInertiaFlash('error');

        Queue::assertNothingPushed();
    });
});

describe('resultado', function () {
    it('un número válido fija vies_validated_at', function () {
        $vies = fakeVies(FakeVatNumberValidator::valid());

        $this->post('/customers', euCustomerForm());

        expect(Customer::query()->sole()->vies_validated_at)->not->toBeNull()
            ->and($vies->checked)->toBe([EU_VAT_NUMBER]);
    });

    it('un número no válido no la fija', function () {
        fakeVies(FakeVatNumberValidator::invalid());

        $this->post('/customers', euCustomerForm());

        expect(Customer::query()->sole()->vies_validated_at)->toBeNull();
    });

    it('si VIES no responde no la fija y el job se reintenta más tarde', function () {
        $vies = fakeVies(FakeVatNumberValidator::unavailable());
        $customer = Customer::factory()->create(['tax_id' => EU_VAT_NUMBER, 'tax_id_type' => 'VAT_EU']);

        $job = (new ValidateVatNumberJob($customer->id))->withFakeQueueInteractions();
        $job->handle($vies, app(Clock::class));

        $job->assertReleased(delay: config('invoice.vies.backoff_seconds')[0]);
        expect($customer->refresh()->vies_validated_at)->toBeNull();
    });

    it('no vuelve a consultar un número validado hace menos de 30 días', function () {
        $vies = fakeVies(FakeVatNumberValidator::valid());
        $customer = Customer::factory()->create([
            'tax_id' => EU_VAT_NUMBER,
            'tax_id_type' => 'VAT_EU',
            'vies_validated_at' => '2026-09-20 10:00:00',
        ]);

        ValidateVatNumberJob::dispatchSync($customer->id);
        expect($vies->checked)->toBe([]);

        ValidateVatNumberJob::dispatchSync($customer->id, force: true);
        expect($vies->checked)->toBe([EU_VAT_NUMBER]);
    });

    it('pasados 30 días sí vuelve a consultar', function () {
        $vies = fakeVies(FakeVatNumberValidator::valid());
        $customer = Customer::factory()->create([
            'tax_id' => EU_VAT_NUMBER,
            'tax_id_type' => 'VAT_EU',
            'vies_validated_at' => '2026-08-01 10:00:00',
        ]);

        ValidateVatNumberJob::dispatchSync($customer->id);

        expect($vies->checked)->toBe([EU_VAT_NUMBER]);
    });

    it('la ficha del cliente muestra el estado', function () {
        fakeVies(FakeVatNumberValidator::unavailable());
        $customer = Customer::factory()->create(['tax_id' => EU_VAT_NUMBER, 'tax_id_type' => 'VAT_EU']);

        inertiaGet("/customers/{$customer->id}")->assertJsonPath('props.vies', ['validated_at' => null, 'status' => 'pending']);

        $customer->update(['vies_validated_at' => now()]);
        inertiaGet("/customers/{$customer->id}")->assertJsonPath('props.vies.status', 'validated');
    });
});

it('un número sin validar en VIES no impide emitir la factura', function () {
    fakeVies(FakeVatNumberValidator::unavailable());
    configuredIssuer();
    Series::factory()->invoices()->create();
    $customer = Customer::factory()->create(['tax_id' => EU_VAT_NUMBER, 'tax_id_type' => 'VAT_EU', 'vies_validated_at' => null]);

    $draft = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
        'customer_id' => $customer->id,
        'lines' => [['position' => 1, 'description' => 'Consultoría', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '21']],
    ]));
    /** @var Invoice $draft */
    $invoice = app(IssueInvoice::class)($draft)->invoice;

    expect($invoice->status)->toBe(DocumentStatus::Issued)
        ->and($invoice->customer_snapshot['tax_id'])->toBe(EU_VAT_NUMBER);
});
