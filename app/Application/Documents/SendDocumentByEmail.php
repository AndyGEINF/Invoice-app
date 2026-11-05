<?php

declare(strict_types=1);

namespace App\Application\Documents;

use App\Application\Documents\Data\SendData;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Exceptions\CannotSendDocument;
use App\Domain\Shared\Contracts\Clock;
use App\Jobs\SendDocumentEmailJob;
use Illuminate\Support\Facades\DB;

/**
 * Registra un envío por email y lo encola. Cada envío es una fila nueva, también
 * los reenvíos y los reintentos de uno fallido, y el estado del documento no
 * cambia hasta que el proveedor acepta el mensaje ({@see SendDocumentEmailJob}).
 */
final readonly class SendDocumentByEmail
{
    public function __construct(private Clock $clock) {}

    public function __invoke(Document $document, SendData $data): DocumentSend
    {
        if (! $document->isSendable()) {
            throw CannotSendDocument::draft();
        }

        return DB::transaction(function () use ($document, $data): DocumentSend {
            $send = $document->sends()->create([
                'to' => $data->to,
                'cc' => $data->cc === [] ? null : $data->cc,
                'subject' => $data->subject,
                'body' => $data->body,
                'queued_at' => $this->clock->now(),
            ]);

            SendDocumentEmailJob::dispatch($send->id);

            return $send;
        });
    }
}
