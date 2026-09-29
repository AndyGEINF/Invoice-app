<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\DocumentTax;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\ExemptionCode;
use App\Domain\Documents\Enums\TaxType;
use App\Domain\Documents\InvoiceTypeResolver;
use App\Domain\Documents\SnapshotFactory;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Address;
use Illuminate\Support\Facades\Storage;

/**
 * Todo lo que imprime la plantilla del PDF (contracts/web-routes.md §Plantilla),
 * ya formateado en español. La vista solo pinta.
 *
 * Un documento emitido se imprime con sus snapshots congelados. Un borrador aún
 * no los tiene: se usan los datos actuales del emisor y del cliente, sin
 * guardarlos, y la plantilla lo marca como borrador sin validez fiscal.
 */
final readonly class PrintableDocument
{
    /**
     * @param  array<string, mixed>  $issuer
     * @param  array<string, mixed>|null  $customer
     * @param  list<array{label: string, value: string}>  $dates
     * @param  list<array<string, string>>  $lines
     * @param  list<array<string, string>>  $taxes
     * @param  list<array{label: string, value: string}>  $totals
     * @param  list<string>  $exemptions
     */
    public function __construct(
        public string $title,
        public ?string $number,
        public bool $isDraft,
        public array $dates,
        public array $issuer,
        public ?array $customer,
        public ?string $rectifies,
        public array $lines,
        public ?string $globalDiscount,
        public array $taxes,
        public array $totals,
        public string $total,
        public array $exemptions,
        public ?string $notes,
        public ?string $footer,
    ) {}

    public static function from(Document $document): self
    {
        $document->loadMissing(['lines', 'taxes', 'customer', 'rectifies']);

        $snapshots = new SnapshotFactory;
        $issuer = $document->issuer_snapshot ?? $snapshots->issuer(Issuer::current());
        $customer = $document->customer_snapshot
            ?? ($document->customer !== null ? $snapshots->customer($document->customer) : null);

        return new self(
            title: self::title($document),
            number: $document->full_number,
            isDraft: ! $document->hasNumber(),
            dates: self::dates($document),
            issuer: self::party($issuer) + [
                'contact_name' => self::contactName($issuer),
                'contact_lines' => array_values(array_filter([$issuer['email'] ?? null, $issuer['phone'] ?? null, $issuer['website'] ?? null])),
                'logo' => self::logoDataUri($issuer['logo_path'] ?? null),
            ],
            customer: $customer === null ? null : self::party($customer) + [
                'trade_name' => ($customer['trade_name'] ?? null) !== $customer['legal_name'] ? ($customer['trade_name'] ?? null) : null,
            ],
            rectifies: self::rectificationReference($document),
            lines: $document->lines->map(fn (DocumentLine $line): array => self::line($document, $line))->all(),
            globalDiscount: $document->global_discount_percent->isZero()
                ? null
                : SpanishFormat::percentage($document->global_discount_percent),
            taxes: self::taxRows($document),
            totals: self::totals($document),
            total: $document->total->format(),
            exemptions: self::exemptionTexts($document),
            notes: $document->notes,
            footer: $issuer['invoice_footer'] ?? null,
        );
    }

    private static function title(Document $document): string
    {
        if ($document->type === DocumentType::Quote) {
            return 'Presupuesto';
        }

        if ($document instanceof CreditNote) {
            return 'Factura rectificativa';
        }

        $type = $document->invoice_type ?? (new InvoiceTypeResolver)->resolve($document);

        return $type->isSimplified() ? 'Factura simplificada' : 'Factura';
    }

    /** @return list<array{label: string, value: string}> */
    private static function dates(Document $document): array
    {
        $isQuote = $document->type === DocumentType::Quote;
        $dates = [];

        if ($document->issue_date !== null) {
            $dates[] = ['label' => $isQuote ? 'Fecha' : 'Fecha de expedición', 'value' => SpanishFormat::date($document->issue_date)];
        }

        if ($document->operation_date !== null && ! $document->operation_date->equalTo($document->issue_date)) {
            $dates[] = ['label' => 'Fecha de operación', 'value' => SpanishFormat::date($document->operation_date)];
        }

        if ($isQuote && $document->valid_until !== null) {
            $dates[] = ['label' => 'Válido hasta', 'value' => SpanishFormat::date($document->valid_until)];
        }

        if (! $isQuote && $document->due_date !== null) {
            $dates[] = ['label' => 'Vencimiento', 'value' => SpanishFormat::date($document->due_date)];
        }

        return $dates;
    }

    /**
     * Nombre, NIF y dirección de un snapshot (emisor o cliente).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private static function party(array $snapshot): array
    {
        return [
            'name' => (string) ($snapshot['legal_name'] ?? ''),
            'tax_id' => $snapshot['tax_id'] ?? null,
            'address_lines' => self::addressLines(Address::fromArray($snapshot['address'] ?? [])),
        ];
    }

    /** La persona de contacto solo se imprime si no es ya el nombre fiscal. */
    private static function contactName(array $issuer): ?string
    {
        $contact = $issuer['contact_name'] ?? null;

        return $contact !== null && $contact !== ($issuer['legal_name'] ?? null) ? $contact : null;
    }

    /** @return list<string> */
    private static function addressLines(Address $address): array
    {
        $locality = trim($address->postalCode.' '.$address->city);

        if ($address->province !== '' && $address->province !== $address->city) {
            $locality .= ' ('.$address->province.')';
        }

        return array_values(array_filter([
            $address->street,
            $locality,
            $address->isSpanish() ? '' : $address->country,
        ]));
    }

    /** Logotipo incrustado: el PDF se genera sin acceso a la red. */
    private static function logoDataUri(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk(config('invoice.storage.logos_disk'));

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode((string) $disk->get($path));
    }

    private static function rectificationReference(Document $document): ?string
    {
        $original = $document->rectifies;

        if ($original === null) {
            return null;
        }

        $reference = sprintf(
            'Rectifica a la factura %s de fecha %s',
            $original->full_number ?? '—',
            SpanishFormat::date($original->issue_date) ?? '—',
        );

        return $document->rectification_reason !== null && $document->rectification_reason !== ''
            ? $reference.' — '.$document->rectification_reason
            : $reference;
    }

    /** @return array<string, string> */
    private static function line(Document $document, DocumentLine $line): array
    {
        return [
            'description' => $line->description,
            'quantity' => SpanishFormat::quantity($line->quantity),
            'unit' => $line->unit,
            'unit_price' => SpanishFormat::unitPrice($line->unit_price, $document->currency),
            'discount' => $line->discount_percent->isZero() ? '' : SpanishFormat::percentage($line->discount_percent),
            'vat' => $line->exemption_code !== null
                ? $line->exemption_code->value
                : SpanishFormat::percentage($line->vat_rate),
            'amount' => $line->line_base->format(),
        ];
    }

    /**
     * Desglose por tipo: IVA (con su exención si la hay), recargo y retención.
     *
     * @return list<array<string, string>>
     */
    private static function taxRows(Document $document): array
    {
        return $document->taxes
            ->sortBy(static fn (DocumentTax $tax): string => array_search($tax->tax_type, TaxType::cases(), true).'-'.$tax->rate)
            ->values()
            ->map(static function (DocumentTax $tax): array {
                $label = $tax->tax_type->label().' '.SpanishFormat::percentage($tax->rate);

                if ($tax->exemption_code !== null) {
                    $label = $tax->tax_type->label().' — '.$tax->exemption_code->value;
                }

                return [
                    'label' => $label,
                    'base' => $tax->base->format(),
                    'amount' => ($tax->tax_type->isWithholding() ? $tax->amount->negate() : $tax->amount)->format(),
                ];
            })
            ->all();
    }

    /** @return list<array{label: string, value: string}> */
    private static function totals(Document $document): array
    {
        $totals = [
            ['label' => 'Base imponible', 'value' => $document->taxable_base->format()],
            ['label' => 'IVA', 'value' => $document->vat_total->format()],
        ];

        if (! $document->surcharge_total->isZero()) {
            $totals[] = ['label' => 'Recargo de equivalencia', 'value' => $document->surcharge_total->format()];
        }

        if (! $document->irpf_total->isZero()) {
            $totals[] = ['label' => 'Retención IRPF', 'value' => $document->irpf_total->negate()->format()];
        }

        return $totals;
    }

    /**
     * Texto legal de cada causa de exención usada, una sola vez.
     *
     * @return list<string>
     */
    private static function exemptionTexts(Document $document): array
    {
        return $document->lines
            ->map(static fn (DocumentLine $line): ?ExemptionCode => $line->exemption_code)
            ->filter()
            ->unique(static fn (ExemptionCode $code): string => $code->value)
            ->map(static fn (ExemptionCode $code): string => $code->value.': '.$code->legalText())
            ->values()
            ->all();
    }
}
