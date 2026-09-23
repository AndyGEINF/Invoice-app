<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Product;
use App\Domain\Customers\Customer;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\Money;
use App\Domain\Shared\Percentage;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Carbon\CarbonImmutable;
use Database\Factories\IssuerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de demostración: el emisor "Demo SL" con su logotipo, clientes de cada
 * tipo, un catálogo, facturas emitidas (una vencida y otra cobrada) y
 * presupuestos.
 *
 * Solo para desarrollo y demos: `php artisan db:seed --class=DemoSeeder`.
 */
final class DemoSeeder extends Seeder
{
    private const int CUSTOMERS = 6;

    private const int PRODUCTS = 12;

    private const int ISSUED_INVOICES = 5;

    private const int QUOTES = 3;

    private const string VAT_RATE = '21.00';

    public function run(): void
    {
        $this->call(InstallSeeder::class);

        $this->configureIssuer();
        $customers = $this->createCustomers();
        $products = $this->createProducts();

        $this->createIssuedInvoices($customers, $products);
        $this->createQuotes($customers, $products);
    }

    private function configureIssuer(): void
    {
        $disk = Storage::disk(config('invoice.storage.logos_disk'));
        $disk->put(IssuerFactory::TEST_LOGO_PATH, (string) file_get_contents(__DIR__.'/assets/demo-logo.png'));

        Issuer::current()->fill(Issuer::factory()->complete()->raw())->save();
    }

    /** @return list<Customer> */
    private function createCustomers(): array
    {
        return [
            Customer::factory()->withIrpf()->create(['legal_name' => 'Talleres Pérez SL']),
            Customer::factory()->withSurcharge()->create(['legal_name' => 'Ferretería La Esquina SL']),
            Customer::factory()->intraCommunity()->create(),
            Customer::factory()->individual()->create(['legal_name' => 'Laura Martín Gil']),
            ...Customer::factory()->count(self::CUSTOMERS - 4)->create()->all(),
        ];
    }

    /** @return list<Product> */
    private function createProducts(): array
    {
        return [
            Product::factory()->service()->priced('40')->create(['name' => 'Hora de reparación', 'sku' => 'SRV-0001']),
            Product::factory()->service()->priced('33.333')->create(['name' => 'Hora de consultoría', 'sku' => 'SRV-0002']),
            Product::factory()->priced('12.50')->vat('10.00')->create(['name' => 'Menú del día', 'sku' => 'PRD-0001']),
            ...Product::factory()->count(self::PRODUCTS - 3)->create()->all(),
        ];
    }

    /**
     * Facturas emitidas con una línea cada una.
     *
     * No usa el caso de uso de emisión (aún no existe en esta fase del proyecto):
     * crea el borrador con su línea, lo cuadra con un único tipo de IVA y lo pasa
     * a emitido, numerándolo de forma correlativa en la serie F.
     *
     * @param  list<Customer>  $customers
     * @param  list<Product>  $products
     */
    private function createIssuedInvoices(array $customers, array $products): void
    {
        $series = Series::defaultFor(DocumentType::Invoice);
        $today = CarbonImmutable::today();

        // Al cliente intracomunitario no se le repercute IVA español: queda fuera
        // de estas facturas de ejemplo, que usan el IVA general.
        $billable = array_values(array_filter(
            $customers,
            static fn (Customer $customer): bool => ! ($customer->taxId()?->isEuVat() ?? false),
        ));

        for ($i = 0; $i < self::ISSUED_INVOICES; $i++) {
            $customer = $billable[$i % count($billable)];
            $issueDate = $today->subDays(10 * (self::ISSUED_INVOICES - $i));

            DB::transaction(function () use ($series, $customer, $products, $i, $issueDate): void {
                $invoice = Invoice::query()->create(['customer_id' => $customer->id]);
                $totals = $this->addSingleLine($invoice, $products[$i % count($products)], (string) ($i + 1));

                $number = $series->next_number;
                $year = (int) $issueDate->format('Y');

                $invoice->update([
                    ...$totals,
                    'status' => DocumentStatus::Issued,
                    'series_id' => $series->id,
                    'number' => $number,
                    'fiscal_year' => $series->fiscalYearFor($year),
                    'full_number' => DocumentNumber::of($series->prefix, $number, $series->padding, $year)->full(),
                    'issue_date' => $issueDate,
                    'due_date' => $issueDate->addDays($customer->payment_terms_days),
                    'issuer_snapshot' => Issuer::current()->toSnapshot(),
                    'customer_snapshot' => $customer->toSnapshot(),
                    'invoice_type' => InvoiceType::F1,
                    'issued_at' => $issueDate,
                ]);

                $series->update(['next_number' => $number + 1, 'year' => $year]);
            });
        }

        // Una cobrada (la más reciente) y una vencida (la más antigua, con plazo corto).
        $latest = Invoice::query()->latest('issue_date')->first();
        $latest?->update(['paid_at' => $today, 'paid_note' => 'Transferencia']);
    }

    /**
     * @param  list<Customer>  $customers
     * @param  list<Product>  $products
     */
    private function createQuotes(array $customers, array $products): void
    {
        $statuses = [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Accepted];

        for ($i = 0; $i < self::QUOTES; $i++) {
            $quote = Quote::query()->create([
                'customer_id' => $customers[$i]->id,
                'issue_date' => CarbonImmutable::today(),
                'valid_until' => CarbonImmutable::today()->addDays((int) config('invoice.quote.default_validity_days')),
            ]);

            $totals = $this->addSingleLine($quote, $products[$i], (string) (($i + 1) * 2));
            $quote->update([...$totals, 'status' => $statuses[$i]]);
        }
    }

    /**
     * Añade una línea al IVA general y devuelve los totales del documento.
     *
     * @return array<string, Money>
     */
    private function addSingleLine(Invoice|Quote $document, Product $product, string $quantity): array
    {
        $qty = Quantity::of($quantity);
        $price = UnitPrice::fromThousandths($product->unit_price->thousandths);
        $vatRate = Percentage::of(self::VAT_RATE);

        $base = Money::fromDecimal($price->times($qty));
        $vat = $base->multiplyBy($vatRate);

        $document->lines()->create([
            'position' => 1,
            'product_id' => $product->id,
            'description' => $product->lineDescription(),
            'quantity' => $qty,
            'unit' => $product->unit,
            'unit_price' => $price,
            'vat_rate' => $vatRate,
            'line_base' => $base,
        ]);

        $document->taxes()->create([
            'tax_type' => TaxType::Vat,
            'rate' => $vatRate,
            'base' => $base,
            'amount' => $vat,
        ]);

        return [
            'taxable_base' => $base,
            'vat_total' => $vat,
            'total' => $base->plus($vat),
        ];
    }
}
