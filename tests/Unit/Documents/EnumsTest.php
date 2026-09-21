<?php

declare(strict_types=1);

use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Enums\InvoiceType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Enums\RectificationType;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Shared\Enums\VatRegime;

describe('DocumentStatus', function () {
    it('solo permite las transiciones del ciclo fiscal', function () {
        expect(DocumentStatus::Draft->allows(DocumentStatus::Issued))->toBeTrue()
            ->and(DocumentStatus::Issued->allows(DocumentStatus::Sent))->toBeTrue()
            ->and(DocumentStatus::Issued->allows(DocumentStatus::Rectified))->toBeTrue()
            ->and(DocumentStatus::Sent->allows(DocumentStatus::Rectified))->toBeTrue();
    });

    it('nunca vuelve a borrador', function () {
        expect(DocumentStatus::Issued->allows(DocumentStatus::Draft))->toBeFalse()
            ->and(DocumentStatus::Sent->allows(DocumentStatus::Draft))->toBeFalse()
            ->and(DocumentStatus::Rectified->allowedTransitions())->toBe([]);
    });

    it('solo el borrador es editable', function () {
        expect(DocumentStatus::Draft->isEditable())->toBeTrue()
            ->and(DocumentStatus::Issued->isEditable())->toBeFalse()
            ->and(DocumentStatus::Issued->isIssued())->toBeTrue();
    });
});

describe('QuoteStatus', function () {
    it('sigue el ciclo comercial', function () {
        expect(QuoteStatus::Draft->allows(QuoteStatus::Sent))->toBeTrue()
            ->and(QuoteStatus::Sent->allows(QuoteStatus::Accepted))->toBeTrue()
            ->and(QuoteStatus::Sent->allows(QuoteStatus::Rejected))->toBeTrue()
            ->and(QuoteStatus::Accepted->allows(QuoteStatus::Converted))->toBeTrue()
            ->and(QuoteStatus::Converted->allowedTransitions())->toBe([]);
    });

    it('deja de ser editable al convertirse', function () {
        expect(QuoteStatus::Sent->isEditable())->toBeTrue()
            ->and(QuoteStatus::Converted->isEditable())->toBeFalse();
    });
});

describe('DocumentType', function () {
    it('distingue el documento comercial de los fiscales', function () {
        expect(DocumentType::Quote->isFiscal())->toBeFalse()
            ->and(DocumentType::Invoice->isFiscal())->toBeTrue()
            ->and(DocumentType::CreditNote->isFiscal())->toBeTrue();
    });

    it('conoce su segmento de URL', function () {
        expect(DocumentType::Quote->routeSegment())->toBe('quotes')
            ->and(DocumentType::CreditNote->routeSegment())->toBe('credit-notes');
    });
});

describe('InvoiceType y RectificationType', function () {
    it('reconoce las rectificativas', function () {
        expect(InvoiceType::F1->isRectification())->toBeFalse()
            ->and(InvoiceType::R5->isRectification())->toBeTrue()
            ->and(InvoiceType::F2->isSimplified())->toBeTrue()
            ->and(InvoiceType::rectifications())->toBe([InvoiceType::R1, InvoiceType::R4, InvoiceType::R5]);
    });

    it('asocia la forma de rectificar con su código', function () {
        expect(RectificationType::Substitution->invoiceType())->toBe(InvoiceType::R1)
            ->and(RectificationType::Differences->invoiceType())->toBe(InvoiceType::R4);
    });
});

describe('TaxType', function () {
    it('sabe que el IRPF resta', function () {
        expect(TaxType::Irpf->isWithholding())->toBeTrue()
            ->and(TaxType::Vat->isWithholding())->toBeFalse()
            ->and(TaxType::Surcharge->isWithholding())->toBeFalse();
    });
});

describe('ExemptionCode', function () {
    it('lleva el texto legal que se imprime en la factura', function () {
        expect(ExemptionCode::E1->legalText())->toContain('artículo 20')
            ->and(ExemptionCode::NotSubject->legalText())->toBe('Operación no sujeta al IVA');
    });

    it('alimenta la configuración sin duplicar el texto', function () {
        expect(config('invoice.tax.exemption_codes'))
            ->toHaveKey('E1', ExemptionCode::E1->legalText())
            ->toHaveKey('NS', ExemptionCode::NotSubject->legalText());
    });
});

describe('VatRegime', function () {
    it('identifica el régimen exento', function () {
        expect(VatRegime::Exempt->isExempt())->toBeTrue()
            ->and(VatRegime::General->isExempt())->toBeFalse();
    });
});
