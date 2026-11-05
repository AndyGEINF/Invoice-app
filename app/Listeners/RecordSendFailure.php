<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Documents\DocumentEvent;
use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Events\DocumentEmailFailed;

/** Deja el fallo en el historial. El estado del documento no cambia. */
final class RecordSendFailure
{
    public function handle(DocumentEmailFailed $event): void
    {
        $send = DocumentSend::query()->findOrFail($event->sendId);

        DocumentEvent::query()->create([
            'document_id' => $send->document_id,
            'event' => DocumentEventType::SendFailed,
            'payload' => ['send_id' => $send->id, 'to' => $send->to, 'error' => $send->error],
        ]);
    }
}
