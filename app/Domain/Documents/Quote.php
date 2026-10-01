<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Concerns\IsTypedDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Presupuesto.
 *
 * Documento comercial: se edita y se borra hasta que se convierte en factura. La
 * caducidad no es un estado guardado, se deduce de la fecha de validez.
 *
 * @property QuoteStatus $status
 */
#[UseFactory(QuoteFactory::class)]
final class Quote extends Document
{
    use IsTypedDocument;

    public static function fixedType(): DocumentType
    {
        return DocumentType::Quote;
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'status' => QuoteStatus::class];
    }

    protected static function booted(): void
    {
        self::creating(function (self $quote): void {
            $quote->status ??= QuoteStatus::Draft;
        });
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /** Enviado y con la validez superada: ya no se puede aceptar sin duplicarlo. */
    public function isExpired(?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::today();

        // Se comparan días de calendario, no instantes: así no influye la zona
        // horaria de cada fecha (igual que el scope `expired`).
        return $this->status === QuoteStatus::Sent
            && $this->valid_until !== null
            && $this->valid_until->toDateString() < $today->toDateString();
    }

    public function isConverted(): bool
    {
        return $this->status === QuoteStatus::Converted;
    }

    #[Scope]
    protected function expired(Builder $query, ?CarbonImmutable $today = null): void
    {
        $today ??= CarbonImmutable::today();

        $query->where('status', QuoteStatus::Sent->value)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $today->toDateString());
    }
}
