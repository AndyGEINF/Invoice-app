<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Documents\Enums\RectificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rectificar una factura: cómo (sustitución o diferencias) y por qué. */
final class RectifyInvoiceRequest extends FormRequest
{
    public const int MAX_REASON_LENGTH = 500;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rectification_type' => ['required', Rule::enum(RectificationType::class)],
            'reason' => ['required', 'string', 'max:'.self::MAX_REASON_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'rectification_type' => 'tipo de rectificación',
            'reason' => 'motivo',
        ];
    }

    public function rectificationType(): RectificationType
    {
        return RectificationType::from((string) $this->validated('rectification_type'));
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
