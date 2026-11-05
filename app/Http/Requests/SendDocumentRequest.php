<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Documents\Data\SendData;
use Illuminate\Foundation\Http\FormRequest;

/** Enviar un documento por email: destinatarios, copia, asunto y texto. */
final class SendDocumentRequest extends FormRequest
{
    public const int MAX_SUBJECT_LENGTH = 200;

    public const int MAX_BODY_LENGTH = 5000;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:'.self::MAX_SUBJECT_LENGTH],
            'body' => ['required', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'to' => 'destinatario',
            'to.*' => 'destinatario',
            'cc.*' => 'copia',
            'subject' => 'asunto',
            'body' => 'mensaje',
        ];
    }

    public function toSendData(): SendData
    {
        return new SendData(
            array_values($this->validated('to')),
            array_values($this->validated('cc') ?? []),
            (string) $this->validated('subject'),
            (string) $this->validated('body'),
        );
    }
}
