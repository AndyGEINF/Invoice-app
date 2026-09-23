<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use App\Domain\Shared\DocumentNumber;
use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Serie de numeración de un tipo de documento.
 *
 * El contador solo avanza dentro de la transacción de emisión, con la fila
 * bloqueada (decisión D5). Con documentos emitidos, el prefijo y el código ya
 * no pueden cambiar y el contador solo puede subir.
 *
 * @property string $id
 * @property DocumentType $document_type
 * @property string $code
 * @property string $prefix
 * @property int $padding
 * @property int $next_number
 * @property bool $resets_yearly
 * @property int|null $year
 * @property bool $is_default
 * @property bool $is_active
 */
#[UseFactory(SeriesFactory::class)]
final class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    /** Valor de `fiscal_year` en series que no se reinician cada año. */
    public const int NO_FISCAL_YEAR = 0;

    protected $table = 'series';

    protected $fillable = [
        'document_type',
        'code',
        'prefix',
        'padding',
        'next_number',
        'resets_yearly',
        'year',
        'is_default',
        'is_active',
    ];

    protected $attributes = [
        'padding' => DocumentNumber::DEFAULT_PADDING,
        'next_number' => DocumentNumber::FIRST_NUMBER,
        'resets_yearly' => true,
        'is_default' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'padding' => 'integer',
            'next_number' => 'integer',
            'resets_yearly' => 'boolean',
            'year' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function forType(Builder $query, DocumentType $type): void
    {
        $query->where('document_type', $type->value);
    }

    public static function defaultFor(DocumentType $type): ?self
    {
        return self::query()->forType($type)->where('is_default', true)->first();
    }

    /** Año que se guarda en el documento: el de expedición o 0 si la serie no se reinicia. */
    public function fiscalYearFor(int $issueYear): int
    {
        return $this->resets_yearly ? $issueYear : self::NO_FISCAL_YEAR;
    }

    /** Si tiene algún documento con número, su prefijo y su código quedan fijados. */
    public function hasIssuedDocuments(): bool
    {
        return $this->documents()->whereNotNull('number')->exists();
    }
}
