<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\RectificationType;
use LogicException;

/**
 * Clasifica una factura con el código de la AEAT al emitirla.
 *
 * - F1: factura completa, el cliente tiene identificador fiscal.
 * - F2: factura simplificada, sin identificador fiscal del cliente.
 * - R1 / R4: rectificativa por sustitución o por diferencias.
 * - R5: rectificativa de una factura simplificada.
 */
final class InvoiceTypeResolver
{
    public function resolve(Document $document): InvoiceType
    {
        return match ($document->type) {
            DocumentType::Invoice => $this->resolveInvoice($document),
            DocumentType::CreditNote => $this->resolveCreditNote($document),
            DocumentType::Quote => throw new LogicException('Un presupuesto no tiene clasificación fiscal.'),
        };
    }

    private function resolveInvoice(Document $invoice): InvoiceType
    {
        return $this->customerTaxId($invoice) === null ? InvoiceType::F2 : InvoiceType::F1;
    }

    private function resolveCreditNote(Document $creditNote): InvoiceType
    {
        $original = $creditNote->rectifies;

        if ($original !== null && ($original->invoice_type?->isSimplified() || $original->invoice_type === InvoiceType::R5)) {
            return InvoiceType::R5;
        }

        return ($creditNote->rectification_type ?? RectificationType::Substitution)->invoiceType();
    }

    /** Identificador del cliente: el congelado si ya existe, si no el de la ficha. */
    private function customerTaxId(Document $document): ?string
    {
        $snapshotTaxId = $document->customer_snapshot['tax_id'] ?? null;

        if ($document->customer_snapshot !== null) {
            return $snapshotTaxId !== '' ? $snapshotTaxId : null;
        }

        $taxId = $document->customer?->tax_id;

        return $taxId !== null && $taxId !== '' ? $taxId : null;
    }
}
