<?php

declare(strict_types=1);

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use Database\Factories\IssuerFactory;
use Database\Seeders\DemoSeeder;
use Database\Seeders\InstallSeeder;

describe('InstallSeeder', function () {
    it('crea el emisor vacío y una serie por defecto de cada tipo', function () {
        $this->seed(InstallSeeder::class);

        expect(Issuer::query()->count())->toBe(1)
            ->and(Issuer::current()->isComplete())->toBeFalse()
            ->and(Series::defaultFor(DocumentType::Invoice)?->prefix)->toBe('F')
            ->and(Series::defaultFor(DocumentType::CreditNote)?->prefix)->toBe('R')
            ->and(Series::defaultFor(DocumentType::Quote)?->prefix)->toBe('P');
    });

    it('se puede ejecutar varias veces sin duplicar nada', function () {
        $this->seed(InstallSeeder::class);
        $this->seed(InstallSeeder::class);

        expect(Issuer::query()->count())->toBe(1)
            ->and(Series::query()->count())->toBe(3);
    });

    it('respeta los datos que el usuario ya configuró', function () {
        $this->seed(InstallSeeder::class);
        Issuer::current()->update(['name' => 'Andy Moreno']);
        Series::defaultFor(DocumentType::Invoice)?->update(['next_number' => 42]);

        $this->seed(InstallSeeder::class);

        expect(Issuer::current()->name)->toBe('Andy Moreno')
            ->and(Series::defaultFor(DocumentType::Invoice)?->next_number)->toBe(42);
    });
});

describe('DemoSeeder', function () {
    beforeEach(function () {
        Storage::fake(config('invoice.storage.logos_disk'));
        $this->seed(DemoSeeder::class);
    });

    it('configura el emisor de demostración con su logotipo', function () {
        expect(Issuer::current()->isComplete())->toBeTrue()
            ->and(Issuer::current()->legalName())->toBe('Demo SL');

        Storage::disk(config('invoice.storage.logos_disk'))->assertExists(IssuerFactory::TEST_LOGO_PATH);
    });

    it('emite cinco facturas con numeración correlativa', function () {
        $numbers = Invoice::query()->issued()->orderBy('number')->pluck('number')->all();

        expect($numbers)->toBe([1, 2, 3, 4, 5])
            ->and(Series::defaultFor(DocumentType::Invoice)?->next_number)->toBe(6);
    });

    it('las facturas cuadran con su desglose', function () {
        Invoice::query()->issued()->with('taxes')->get()->each(function (Invoice $invoice): void {
            $vat = $invoice->taxes->sum(fn ($tax) => $tax->amount->cents);

            expect($invoice->vat_total->cents)->toBe($vat)
                ->and($invoice->lines()->count())->toBe(1);
        });
    });

    it('incluye una factura cobrada y alguna vencida', function () {
        $statuses = Invoice::query()->issued()->get()->map(fn (Invoice $i) => $i->paymentStatus());

        expect($statuses)->toContain(PaymentStatus::Paid)
            ->and($statuses)->toContain(PaymentStatus::Overdue);
    });

    it('crea tres presupuestos', function () {
        expect(Quote::query()->count())->toBe(3);
    });
});
