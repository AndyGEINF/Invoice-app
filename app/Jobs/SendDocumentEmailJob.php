<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Documents\RenderDocumentPdf;
use App\Domain\Documents\Contracts\DocumentStorage;
use App\Domain\Documents\DocumentSend;
use App\Domain\Documents\Enums\SendStatus;
use App\Domain\Shared\Contracts\Clock;
use App\Events\DocumentEmailFailed;
use App\Events\DocumentEmailSent;
use App\Mail\DocumentMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Manda un envío registrado con el PDF adjunto; si el PDF aún no existe, lo
 * genera antes. Idempotente: un envío que ya no está en cola no se repite.
 *
 * Agotados los reintentos, {@see self::failed()} lo deja como fallido con el
 * error, sin tocar el estado del documento.
 */
final class SendDocumentEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $backoff;

    public function __construct(public readonly string $sendId)
    {
        $this->tries = (int) config('invoice.email.tries');
        $this->backoff = (int) config('invoice.email.backoff_seconds');
        $this->afterCommit();
    }

    public function handle(RenderDocumentPdf $render, DocumentStorage $storage, Clock $clock): void
    {
        $send = DocumentSend::query()->with('document')->find($this->sendId);

        if ($send === null || $send->status !== SendStatus::Queued) {
            return;
        }

        $document = $render($send->document);

        $message = Mail::to($send->to)
            ->cc($send->cc ?? [])
            ->send(new DocumentMail($send, $storage->get((string) $document->pdf_path), $document->pdfFilename()));

        $send->forceFill([
            'status' => SendStatus::Sent,
            'sent_at' => $clock->now(),
            'provider_message_id' => $message?->getMessageId(),
        ])->save();

        DocumentEmailSent::dispatch($send->id);
    }

    public function failed(Throwable $exception): void
    {
        $send = DocumentSend::query()->find($this->sendId);

        if ($send === null || $send->status !== SendStatus::Queued) {
            return;
        }

        $send->forceFill(['status' => SendStatus::Failed, 'error' => $exception->getMessage()])->save();

        DocumentEmailFailed::dispatch($send->id);
    }
}
