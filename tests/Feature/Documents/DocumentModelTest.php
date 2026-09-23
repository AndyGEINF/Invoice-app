<?php

declare(strict_types=1);

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\PaymentStatus;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Enums\SendStatus;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Documents\Series;
use App\Domain\Shared\Money;
use App\Domain\Shared\Quantity;
use App\Domain\Shared\UnitPrice;
use Carbon\CarbonImmutable;

/** Factura emitida mínima que cumple las restricciones de la base de datos. */
function issuedInvoice(array $overrides = []): Invoice
{
    static $number = 0;
    $number++;

    return Invoice::query()->create([
        'status' => DocumentStatus::Issued,
        'number' => $number,
        'fiscal_year' => 2026,
        'full_number' => sprintf('F2026-%04d', $number),
        'issue_date' => '2026-09-01',
        'due_date' => '2026-10-01',
        'issuer_snapshot' => ['snapshot_version' => 1],
        'taxable_base' => Money::fromCents(10000),
        'vat_total' => Money::fromCents(2100),
        'total' => Money::fromCents(12100),
        ...$overrides,
    ]);
}

describe('tipos de documento', function () {
    it('crea cada tipo con su discriminador y su estado inicial', function () {
        $invoice = Invoice::query()->create();
        $quote = Quote::query()->create();

        expect($invoice->type)->toBe(DocumentType::Invoice)
            ->and($invoice->status)->toBe(DocumentStatus::Draft)
            ->and($quote->type)->toBe(DocumentType::Quote)
            ->and($quote->status)->toBe(QuoteStatus::Draft);
    });

    it('convierte cada fila en su clase concreta al leer como Document', function () {
        $invoice = Invoice::query()->create();
        $quote = Quote::query()->create();

        expect(Document::query()->find($invoice->id))->toBeInstanceOf(Invoice::class)
            ->and(Document::query()->find($quote->id))->toBeInstanceOf(Quote::class);
    });

    it('cada subclase solo ve sus propios documentos', function () {
        Invoice::query()->create();
        Invoice::query()->create();
        Quote::query()->create();

        expect(Invoice::query()->count())->toBe(2)
            ->and(Quote::query()->count())->toBe(1)
            ->and(CreditNote::query()->count())->toBe(0)
            ->and(Document::query()->count())->toBe(3);
    });

    it('lee los importes como Money', function () {
        $invoice = issuedInvoice()->refresh();

        expect($invoice->total)->toBeInstanceOf(Money::class)
            ->and($invoice->total->cents)->toBe(12100)
            ->and($invoice->taxable_base->cents)->toBe(10000);
    });
});

describe('editabilidad', function () {
    it('una factura solo es editable en borrador', function () {
        expect(Invoice::query()->create()->isEditable())->toBeTrue()
            ->and(issuedInvoice()->isEditable())->toBeFalse();
    });

    it('un presupuesto deja de ser editable al convertirse', function () {
        expect(Quote::query()->create(['status' => QuoteStatus::Sent])->isEditable())->toBeTrue()
            ->and(Quote::query()->create(['status' => QuoteStatus::Converted])->isEditable())->toBeFalse();
    });
});

describe('estado de cobro derivado', function () {
    $today = CarbonImmutable::parse('2026-09-23');

    it('no tiene estado de cobro mientras es borrador', function () use ($today) {
        expect(Invoice::query()->create()->paymentStatus($today))->toBeNull();
    });

    it('está pendiente dentro de plazo', function () use ($today) {
        expect(issuedInvoice()->paymentStatus($today))->toBe(PaymentStatus::Unpaid);
    });

    it('está vencida con el plazo superado', function () use ($today) {
        expect(issuedInvoice(['due_date' => '2026-09-01'])->paymentStatus($today))->toBe(PaymentStatus::Overdue);
    });

    it('está cobrada cuando el usuario la marca', function () use ($today) {
        expect(issuedInvoice(['due_date' => '2026-09-01', 'paid_at' => '2026-09-10'])->paymentStatus($today))
            ->toBe(PaymentStatus::Paid);
    });

    it('con total cero se considera cobrada sin marcar nada', function () use ($today) {
        $invoice = issuedInvoice([
            'taxable_base' => Money::zero(),
            'vat_total' => Money::zero(),
            'total' => Money::zero(),
        ]);

        expect($invoice->paymentStatus($today))->toBe(PaymentStatus::Paid);
    });

    it('filtra pendientes, vencidas y cobradas en SQL', function () use ($today) {
        issuedInvoice();
        issuedInvoice(['due_date' => '2026-09-01']);
        issuedInvoice(['paid_at' => '2026-09-10']);
        Invoice::query()->create();

        expect(Invoice::query()->unpaid($today)->count())->toBe(1)
            ->and(Invoice::query()->overdue($today)->count())->toBe(1)
            ->and(Invoice::query()->paid()->count())->toBe(1)
            ->and(Invoice::query()->issued()->count())->toBe(3);
    });
});

describe('caducidad de presupuestos', function () {
    $today = CarbonImmutable::parse('2026-09-23');

    it('caduca un presupuesto enviado con la validez superada', function () use ($today) {
        $caducado = Quote::query()->create(['status' => QuoteStatus::Sent, 'valid_until' => '2026-09-22']);
        $vigente = Quote::query()->create(['status' => QuoteStatus::Sent, 'valid_until' => '2026-09-23']);
        $borrador = Quote::query()->create(['valid_until' => '2026-01-01']);

        expect($caducado->isExpired($today))->toBeTrue()
            ->and($vigente->isExpired($today))->toBeFalse()
            ->and($borrador->isExpired($today))->toBeFalse()
            ->and(Quote::query()->expired($today)->pluck('id')->all())->toBe([$caducado->id]);
    });
});

describe('relaciones', function () {
    it('ordena las líneas por posición', function () {
        $invoice = Invoice::query()->create();

        foreach ([2, 1] as $position) {
            $invoice->lines()->create([
                'position' => $position,
                'description' => "Línea {$position}",
                'quantity' => Quantity::of('1'),
                'unit_price' => UnitPrice::fromDecimal('10'),
                'vat_rate' => '21',
            ]);
        }

        expect($invoice->lines()->pluck('position')->all())->toBe([1, 2])
            ->and($invoice->lines()->first())->toBeInstanceOf(DocumentLine::class);
    });

    it('guarda el desglose, el historial y los envíos', function () {
        $invoice = Invoice::query()->create();

        $invoice->taxes()->create([
            'tax_type' => TaxType::Vat,
            'rate' => '21',
            'base' => Money::fromCents(10000),
            'amount' => Money::fromCents(2100),
        ]);
        $invoice->events()->create(['event' => DocumentEventType::Created]);
        $invoice->sends()->create([
            'to' => ['cliente@test.local'],
            'subject' => 'Factura',
            'body' => 'Adjunta',
        ]);

        expect($invoice->taxes()->first()->amount->cents)->toBe(2100)
            ->and($invoice->events()->first()->event)->toBe(DocumentEventType::Created)
            ->and($invoice->sends()->first()->status)->toBe(SendStatus::Queued)
            ->and($invoice->sends()->first()->to)->toBe(['cliente@test.local']);
    });

    it('enlaza una rectificativa con la factura que corrige', function () {
        $original = issuedInvoice();
        $credit = CreditNote::query()->create([
            'rectifies_id' => $original->id,
            'rectification_type' => 'S',
        ]);

        expect($credit->rectifies)->toBeInstanceOf(Invoice::class)
            ->and($original->rectifications()->pluck('id')->all())->toBe([$credit->id]);
    });
});

describe('Series', function () {
    it('encuentra la serie por defecto de cada tipo', function () {
        Series::query()->create(['document_type' => DocumentType::Invoice, 'code' => 'F', 'prefix' => 'F', 'is_default' => true]);
        Series::query()->create(['document_type' => DocumentType::Invoice, 'code' => 'T', 'prefix' => 'TALLER']);

        expect(Series::defaultFor(DocumentType::Invoice)?->code)->toBe('F')
            ->and(Series::defaultFor(DocumentType::Quote))->toBeNull();
    });

    it('usa el año de expedición solo si se reinicia cada año', function () {
        $anual = Series::query()->make(['resets_yearly' => true]);
        $continua = Series::query()->make(['resets_yearly' => false]);

        expect($anual->fiscalYearFor(2026))->toBe(2026)
            ->and($continua->fiscalYearFor(2026))->toBe(Series::NO_FISCAL_YEAR);
    });
});
