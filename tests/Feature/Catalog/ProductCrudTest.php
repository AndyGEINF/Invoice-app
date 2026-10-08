<?php

declare(strict_types=1);

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Product;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use App\Http\Queries\CatalogIndexQuery;
use Illuminate\Support\Facades\DB;

/** @return array<string, mixed> */
function productForm(array $overrides = []): array
{
    return [
        'type' => 'service',
        'name' => 'Hora de consultoría',
        'unit_price' => '33.333',
        'unit' => 'h',
        'vat_rate' => '21',
        ...$overrides,
    ];
}

describe('alta', function () {
    it('guarda el precio en milésimas sin pasar por float', function (string $price) {
        $this->post('/products', productForm(['unit_price' => $price]))->assertRedirect('/products')->assertSessionHasNoErrors();

        $product = Product::query()->sole();
        expect(DB::table('products')->where('id', $product->id)->value('unit_price'))->toBe(33333)
            ->and($product->unit_price->toDecimalString())->toBe('33.333')
            ->and($product->type)->toBe(ProductType::Service);
    })->with(['con punto' => '33.333', 'con coma' => '33,333']);

    it('rechaza precios negativos o con más de tres decimales', function (string $price) {
        $this->post('/products', productForm(['unit_price' => $price]))->assertSessionHasErrors('unit_price');
    })->with(['-5', '1.2345', 'diez']);

    it('solo admite los tipos de IVA configurados', function () {
        $this->post('/products', productForm(['vat_rate' => '15']))->assertSessionHasErrors('vat_rate');
        $this->post('/products', productForm(['vat_rate' => '10,0']))->assertSessionHasNoErrors();
    });

    it('un producto exento guarda la causa y el IVA a cero', function () {
        $this->post('/products', productForm(['exemption_code' => 'E1']));

        $product = Product::query()->sole();
        expect($product->exemption_code)->toBe(ExemptionCode::E1)
            ->and($product->vat_rate->isZero())->toBeTrue();
    });
});

describe('referencia (SKU)', function () {
    it('no admite una referencia repetida, sin distinguir mayúsculas', function () {
        Product::factory()->create(['sku' => 'CONS-1']);

        $this->post('/products', productForm(['sku' => 'cons-1']))->assertSessionHasErrors('sku');
        $this->postJson('/products', productForm(['sku' => 'CONS-1']))->assertUnprocessable();
    });

    it('editar un producto sin cambiar su referencia no choca consigo mismo', function () {
        $product = Product::factory()->create(['sku' => 'CONS-1']);

        $this->put("/products/{$product->id}", productForm(['sku' => 'CONS-1', 'unit_price' => '40']))->assertSessionHasNoErrors();

        expect($product->refresh()->unit_price->toDecimalString())->toBe('40.000');
    });

    it('varios productos pueden no tener referencia', function () {
        $this->post('/products', productForm(['sku' => '']));
        $this->post('/products', productForm(['sku' => null, 'name' => 'Otro']))->assertSessionHasNoErrors();

        expect(Product::query()->whereNull('sku')->count())->toBe(2);
    });
});

describe('líneas creadas desde el catálogo', function () {
    beforeEach(function () {
        configuredIssuer();
        Series::factory()->invoices()->create();
    });

    it('copian descripción, precio, unidad e IVA, y cambiar el producto después no las altera', function () {
        $product = Product::factory()->create([
            'name' => 'Hora de consultoría',
            'description' => 'Sesión remota',
            'unit_price' => '33.333',
            'unit' => 'h',
            'vat_rate' => '21.00',
            'type' => ProductType::Service,
        ]);

        // Solo el producto y la cantidad: el resto se copia del catálogo (contracts/web-routes.md).
        $this->post('/invoices', ['lines' => [['position' => 1, 'product_id' => $product->id, 'quantity' => '3']]])->assertSessionHasNoErrors();

        $line = DocumentLine::query()->sole();
        expect($line->product_id)->toBe($product->id)
            ->and($line->description)->toBe("Hora de consultoría\nSesión remota")
            ->and($line->unit_price->toDecimalString())->toBe('33.333')
            ->and($line->unit)->toBe('h')
            ->and((string) $line->vat_rate)->toBe('21.00')
            ->and($line->line_base->cents)->toBe(10000);

        $this->put("/products/{$product->id}", productForm(['name' => 'Hora senior', 'unit_price' => '60', 'vat_rate' => '10']));

        $line->refresh();
        expect($line->description)->toBe("Hora de consultoría\nSesión remota")
            ->and($line->unit_price->toDecimalString())->toBe('33.333')
            ->and((string) $line->vat_rate)->toBe('21.00');
    });

    it('archivar un producto usado no toca las facturas emitidas', function () {
        $product = Product::factory()->create(['name' => 'Mantenimiento', 'description' => null, 'unit_price' => '50', 'vat_rate' => '21.00']);
        $draft = app(CreateDraft::class)(DocumentType::Invoice, DraftData::fromArray([
            'lines' => [['position' => 1, 'product_id' => $product->id, 'quantity' => '1']],
        ]));
        /** @var Invoice $draft */
        $invoice = app(IssueInvoice::class)($draft)->invoice;

        $this->post("/products/{$product->id}/archive")->assertRedirect();

        $line = $invoice->lines()->sole();
        expect($product->refresh()->isArchived())->toBeTrue()
            ->and($line->product_id)->toBe($product->id)
            ->and($line->description)->toBe('Mantenimiento')
            ->and($invoice->refresh()->total->cents)->toBe(6050);
    });
});

describe('listado y búsqueda', function () {
    it('el buscador encuentra por nombre o referencia y nunca devuelve archivados', function () {
        Product::factory()->create(['name' => 'Hora de consultoría', 'sku' => 'CONS-1']);
        Product::factory()->create(['name' => 'Mantenimiento web', 'sku' => 'WEB-9']);
        Product::factory()->create(['name' => 'Consultoría antigua', 'archived_at' => now()]);

        expect($this->getJson('/products/search?q=consul')->json('*.name'))->toBe(['Hora de consultoría'])
            ->and($this->getJson('/products/search?q=web-9')->json('*.name'))->toBe(['Mantenimiento web']);
    });

    it('devuelve como mucho 20 con lo que necesita una línea', function () {
        Product::factory()->count(CatalogIndexQuery::SEARCH_LIMIT + 3)->create();

        $results = $this->getJson('/products/search')->json();

        expect($results)->toHaveCount(CatalogIndexQuery::SEARCH_LIMIT)
            ->and(array_keys($results[0]))->toContain('id', 'name', 'sku', 'unit_price', 'unit', 'vat_rate', 'irpf_applicable', 'line_description');
    });

    it('archiva, restaura y separa activos de archivados', function () {
        $product = Product::factory()->create();
        Product::factory()->create();

        $this->post("/products/{$product->id}/archive");
        inertiaGet('/products')->assertJsonCount(1, 'props.products.data')->assertJsonPath('props.counts', ['active' => 1, 'archived' => 1]);

        $this->post("/products/{$product->id}/restore");
        inertiaGet('/products')->assertJsonCount(2, 'props.products.data');
    });
});
