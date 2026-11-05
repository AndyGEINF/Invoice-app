<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Enums\DocumentEventType;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Events\DocumentEmailSent;
use Illuminate\Support\Facades\DB;

/**
 * Apunta el envío aceptado. La primera vez, una factura emitida pasa a "enviada"
 * y guarda la fecha; un reenvío solo queda en el historial.
 */
final class MarkDocumentAsSent
{
    public function handle(DocumentEmailSent $event): void
    {
        $send = DocumentSend::query()->with('document')->findOrFail($event->sendId);
        $document = $send->document;

        DB::transaction(function () use ($send, $document): void {
            $document->sent_at ??= $send->sent_at;

            if ($document->status instanceof DocumentStatus && $document->status->allows(DocumentStatus::Sent)) {
                $document->status = DocumentStatus::Sent;
            }

            $document->save();

            $document->events()->create([
                'event' => DocumentEventType::Sent,
                'payload' => ['send_id' => $send->id, 'to' => $send->to],
            ]);
        });
    }
}
