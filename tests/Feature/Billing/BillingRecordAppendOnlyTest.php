<?php

declare(strict_types=1);

use App\Domain\Billing\BillingRecord;
use App\Domain\Billing\Exceptions\BillingRecordIsAppendOnly;
use App\Domain\Documents\Invoice;
use App\Domain\Shared\Money;
use Illuminate\Database\QueryException;

function newBillingRecord(): BillingRecord
{
    $invoice = Invoice::query()->create();

    return BillingRecord::query()->create([
        'document_id' => $invoice->id,
        'record_type' => 'alta',
        'invoice_type' => 'F1',
        'issuer_tax_id' => 'B12345674',
        'series_number' => 'F2026-0001',
        'issue_date' => '2026-09-23',
        'total_amount' => Money::fromCents(12100),
        'tax_amount' => Money::fromCents(2100),
        'hash' => str_repeat('a', 64),
        'hashed_at' => now(),
        'software_id' => 'INVOICE',
        'software_version' => '1.0',
        'installation_number' => '1',
        'idempotency_key' => (string) Str::uuid7(),
    ]);
}

describe('billing_records es append-only', function () {
    it('permite añadir registros', function () {
        $record = newBillingRecord();

        expect(BillingRecord::query()->count())->toBe(1)
            ->and($record->refresh()->total_amount->cents)->toBe(12100);
    });

    it('el modelo impide modificarlo', function () {
        newBillingRecord()->update(['hash' => str_repeat('b', 64)]);
    })->throws(BillingRecordIsAppendOnly::class);

    it('el modelo impide borrarlo', function () {
        newBillingRecord()->delete();
    })->throws(BillingRecordIsAppendOnly::class);

    it('la base de datos impide modificarlo aunque se salte el modelo', function () {
        $record = newBillingRecord();

        DB::table('billing_records')->where('id', $record->id)->update(['hash' => 'x']);
    })->throws(QueryException::class, 'append-only');

    it('la base de datos impide borrarlo con SQL crudo', function () {
        newBillingRecord();

        DB::statement('DELETE FROM billing_records');
    })->throws(QueryException::class, 'append-only');

    it('la base de datos impide vaciar la tabla con TRUNCATE', function () {
        newBillingRecord();

        DB::statement('TRUNCATE billing_records');
    })->throws(QueryException::class, 'append-only');
});
