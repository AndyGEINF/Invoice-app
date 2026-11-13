<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Series\Data\SeriesData;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Series;
use App\Domain\Shared\DocumentNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar una serie. Código y prefijo se guardan en mayúsculas, como
 * aparecen en el número (F2026-0001).
 */
final class SeriesRequest extends FormRequest
{
    public const int MAX_CODE_LENGTH = 10;

    public const int MAX_PREFIX_LENGTH = 10;

    /** El contador es un `integer` de PostgreSQL. */
    public const int MAX_NEXT_NUMBER = 2_147_483_647;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'prefix' => mb_strtoupper(trim((string) $this->input('prefix'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Series|null $series */
        $series = $this->route('series');

        return [
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'code' => [
                'required',
                'alpha_num:ascii',
                'max:'.self::MAX_CODE_LENGTH,
                Rule::unique('series', 'code')->ignore($series?->getKey()),
            ],
            'prefix' => ['required', 'regex:/^[A-Z0-9\/-]+$/', 'max:'.self::MAX_PREFIX_LENGTH],
            'padding' => ['required', 'integer', 'between:'.DocumentNumber::MIN_PADDING.','.DocumentNumber::MAX_PADDING],
            'next_number' => ['required', 'integer', 'min:'.DocumentNumber::FIRST_NUMBER, 'max:'.self::MAX_NEXT_NUMBER],
            'resets_yearly' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ya hay una serie con ese código.',
            'code.alpha_num' => 'El código solo puede llevar letras y números, sin espacios.',
            'prefix.regex' => 'El prefijo solo puede llevar letras, números, guiones y barras.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'document_type' => 'tipo de documento',
            'code' => 'código',
            'prefix' => 'prefijo',
            'padding' => 'dígitos',
            'next_number' => 'siguiente número',
            'resets_yearly' => 'reinicio anual',
            'is_default' => 'serie por defecto',
            'is_active' => 'activa',
        ];
    }

    public function toSeriesData(): SeriesData
    {
        return new SeriesData(
            documentType: DocumentType::from((string) $this->validated('document_type')),
            code: (string) $this->validated('code'),
            prefix: (string) $this->validated('prefix'),
            padding: (int) $this->validated('padding'),
            nextNumber: (int) $this->validated('next_number'),
            resetsYearly: (bool) $this->validated('resets_yearly'),
            isDefault: (bool) $this->validated('is_default'),
            isActive: (bool) $this->validated('is_active'),
        );
    }
}
