<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Documents\Document;
use App\Http\Resources\Concerns\PresentsDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de un listado de documentos (`DocumentRow`).
 *
 * @property Document $resource
 */
final class DocumentRow extends JsonResource
{
    use PresentsDocuments;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $document = $this->resource;
        $paymentStatus = self::paymentStatusOf($document);

        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'payment_status' => $paymentStatus?->value,
            'payment_status_label' => $paymentStatus?->label(),
            'full_number' => $document->full_number,
            'issue_date' => self::date($document->issue_date),
            'due_date' => self::date($document->due_date),
            'customer_name' => self::customerName($document),
            'paid_at' => self::date($document->paid_at),
            ...self::amount('total', $document->total),
        ];
    }
}
