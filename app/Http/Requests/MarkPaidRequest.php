<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Marcar como cobrada: la fecha del cobro (hoy o antes) y una nota corta
 * opcional ("Transferencia", "Bizum"…). No hay importes ni formas de pago.
 */
final class MarkPaidRequest extends FormRequest
{
    public const int MAX_NOTE_LENGTH = 200;

    private const string DATE_FORMAT = 'Y-m-d';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $today = app(Clock::class)->today()->toDateString();

        return [
            'paid_on' => ['required', 'date_format:'.self::DATE_FORMAT, 'before_or_equal:'.$today],
            'note' => ['nullable', 'string', 'max:'.self::MAX_NOTE_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['paid_on' => 'fecha de cobro', 'note' => 'nota'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['paid_on.before_or_equal' => 'La fecha de cobro no puede ser posterior a hoy.'];
    }

    public function paidOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::DATE_FORMAT, (string) $this->validated('paid_on'))->startOfDay();
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }
}
