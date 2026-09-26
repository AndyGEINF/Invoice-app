<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Documents\CreateDraft;
use App\Application\Documents\Data\DraftData;
use App\Application\Documents\IssueInvoice;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Crea y emite una factura de una línea en la serie y para el cliente dados.
 *
 * Existe para el test de concurrencia (T045), que lanza decenas de procesos a
 * la vez para comprobar que la numeración no se repite ni deja huecos. Emite
 * facturas de verdad, así que en producción está desactivado.
 */
#[Signature('invoice:issue-test-invoice {series : Id de la serie} {customer : Id del cliente}')]
#[Description('Crea y emite una factura de prueba de una línea (solo local y testing)')]
final class IssueTestInvoice extends Command
{
    /** Entornos donde se permite emitir facturas de prueba. */
    public const array ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    private const array TEST_LINE = [
        'position' => 1,
        'description' => 'Factura de prueba de concurrencia',
        'quantity' => '1',
        'unit_price' => '10.000',
        'vat_rate' => '21.00',
    ];

    public function handle(CreateDraft $createDraft, IssueInvoice $issueInvoice): int
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            $this->error('Este comando solo se puede usar en los entornos: '.implode(', ', self::ALLOWED_ENVIRONMENTS).'.');

            return self::FAILURE;
        }

        $draft = $createDraft(DocumentType::Invoice, DraftData::fromArray([
            'series_id' => (string) $this->argument('series'),
            'customer_id' => (string) $this->argument('customer'),
            'lines' => [self::TEST_LINE],
        ]));

        /** @var Invoice $invoice */
        $invoice = $issueInvoice($draft)->invoice;

        $this->line((string) $invoice->full_number);

        return self::SUCCESS;
    }
}
