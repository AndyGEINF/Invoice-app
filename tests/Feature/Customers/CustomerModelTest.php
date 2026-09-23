<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Product;
use App\Domain\Customers\Customer;
use App\Domain\Customers\Enums\CustomerKind;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\TaxIdType;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\UnitPrice;
use Illuminate\Database\QueryException;

function newCustomer(array $overrides = []): Customer
{
    return Customer::query()->create([
        'kind' => CustomerKind::Business,
        'legal_name' => 'Talleres Pérez SL',
        'tax_id' => 'B12345674',
        'tax_id_type' => TaxIdType::CIF,
        'billing_address' => Address::of('Calle Mayor 1', 'Girona', '17001', 'Girona'),
        'email' => 'admin@talleres.test',
        ...$overrides,
    ]);
}

describe('Customer', function () {
    it('guarda y lee sus value objects', function () {
        $customer = newCustomer(['default_vat_rate' => '21'])->refresh();

        expect($customer->kind)->toBe(CustomerKind::Business)
            ->and($customer->billing_address)->toBeInstanceOf(Address::class)
            ->and($customer->billing_address->city)->toBe('Girona')
            ->and($customer->default_vat_rate?->value)->toBe('21.00')
            ->and($customer->payment_terms_days)->toBe(30)
            ->and($customer->taxId()?->isValid())->toBeTrue();
    });

    it('la base de datos exige NIF a las empresas', function () {
        newCustomer(['tax_id' => null, 'tax_id_type' => null]);
    })->throws(QueryException::class);

    it('permite particulares sin NIF', function () {
        $customer = newCustomer(['kind' => CustomerKind::Individual, 'tax_id' => null, 'tax_id_type' => null]);

        expect($customer->hasTaxId())->toBeFalse();
    });

    it('solo aplica IRPF a empresas y profesionales', function () {
        expect(newCustomer(['irpf_applies' => true])->appliesIrpf())->toBeTrue()
            ->and(newCustomer(['kind' => CustomerKind::Individual, 'tax_id' => null, 'irpf_applies' => true])->appliesIrpf())->toBeFalse();
    });

    it('envía los documentos al contacto por defecto', function () {
        $customer = newCustomer();
        $customer->contacts()->create(['name' => 'Ana', 'email' => 'ana@talleres.test']);
        $customer->contacts()->create(['name' => 'Luis', 'email' => 'luis@talleres.test', 'is_default' => true]);

        expect($customer->billingEmail())->toBe('luis@talleres.test');
    });

    it('usa su propio email si no tiene contactos', function () {
        expect(newCustomer()->billingEmail())->toBe('admin@talleres.test');
    });

    it('se archiva en vez de borrarse', function () {
        $customer = newCustomer();
        $customer->archive();

        expect($customer->isArchived())->toBeTrue()
            ->and(Customer::query()->active()->count())->toBe(0)
            ->and(Customer::query()->archived()->count())->toBe(1);

        $customer->restore();

        expect(Customer::query()->active()->count())->toBe(1);
    });

    it('genera un snapshot con los datos que salen en la factura', function () {
        $snapshot = newCustomer(['irpf_applies' => true])->toSnapshot();

        expect($snapshot)->toMatchArray([
            'snapshot_version' => 1,
            'legal_name' => 'Talleres Pérez SL',
            'tax_id' => 'B12345674',
            'irpf_applies' => true,
            'payment_terms_days' => 30,
        ])->and($snapshot['address']['city'])->toBe('Girona');
    });
});

describe('Product', function () {
    it('guarda el precio en milésimas y el IVA como porcentaje', function () {
        $product = Product::query()->create([
            'type' => ProductType::Service,
            'name' => 'Hora de reparación',
            'unit_price' => '33.333',
            'unit' => 'h',
            'vat_rate' => '21',
        ])->refresh();

        expect($product->unit_price)->toBeInstanceOf(UnitPrice::class)
            ->and($product->unit_price->thousandths)->toBe(33333)
            ->and($product->vat_rate)->toBeInstanceOf(Percentage::class)
            ->and($product->vat_rate->value)->toBe('21.00')
            ->and($product->type)->toBe(ProductType::Service);
    });

    it('guarda la causa de exención', function () {
        $product = Product::query()->create([
            'type' => ProductType::Service,
            'name' => 'Formación reglada',
            'unit_price' => '100',
            'vat_rate' => '0',
            'exemption_code' => ExemptionCode::E1,
        ])->refresh();

        expect($product->exemption_code)->toBe(ExemptionCode::E1);
    });

    it('la base de datos rechaza precios negativos', function () {
        DB::table('products')->insert([
            'id' => (string) Str::uuid7(),
            'type' => 'product',
            'name' => 'Negativo',
            'unit_price' => -1,
            'vat_rate' => 21,
        ]);
    })->throws(QueryException::class);

    it('se archiva y se restaura', function () {
        $product = Product::query()->create([
            'type' => ProductType::Product,
            'name' => 'Tornillo',
            'unit_price' => '0.150',
            'vat_rate' => '21',
        ]);

        $product->archive();

        expect(Product::query()->active()->count())->toBe(0);
    });
});
