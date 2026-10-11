<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Application\Documents\TransitionQuote;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Enums\QuoteStatus;
use App\Domain\Documents\Quote;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\DocumentPageProps;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Enviar, aceptar o rechazar un presupuesto (`POST /quotes/{id}/{transition}`).
 * Las reglas (número al enviar, caducidad al aceptar…) las aplica
 * {@see TransitionQuote}; un error de negocio vuelve al documento como
 * `errors.domain`.
 */
final class QuoteTransitionController extends Controller
{
    use DocumentPageProps;

    /** Segmento de la URL → estado al que pasa el presupuesto. */
    public const array TRANSITIONS = [
        'send' => QuoteStatus::Sent,
        'accept' => QuoteStatus::Accepted,
        'reject' => QuoteStatus::Rejected,
    ];

    private const array MESSAGES = [
        'send' => 'Presupuesto %s enviado.',
        'accept' => 'Presupuesto %s aceptado. Ya puedes convertirlo en factura.',
        'reject' => 'Presupuesto %s marcado como rechazado.',
    ];

    public function __invoke(DocumentType $type, Document $document, string $transition, TransitionQuote $transitionQuote): RedirectResponse
    {
        /** @var Quote $document */
        $quote = $transitionQuote($document, self::TRANSITIONS[$transition]);

        Inertia::flash('success', sprintf(self::MESSAGES[$transition], $quote->full_number));

        return to_route('documents.show', self::routeParams($type, $quote));
    }
}
