<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Documents\CreditNote;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentLine;
use App\Domain\Documents\DocumentTax;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Quote;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Tax\SpanishTaxCalculator;
use App\Domain\Tax\TaxCalculator;
use App\Infrastructure\Time\SystemClock;
use App\Observers\DocumentChildObserver;
use App\Observers\DocumentObserver;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Conecta los puertos del dominio con sus adaptadores.
 *
 * El dominio solo conoce interfaces; aquí se decide qué implementación usa cada
 * entorno (constitución, principio III). Los adaptadores de PDF, almacenamiento
 * y VIES se registran cuando se implementan (T074, T086).
 *
 * Los listeners de app/Listeners (p. ej. RecordDocumentEvent) no se registran
 * aquí: Laravel los descubre por el tipo de su método handle, y registrarlos
 * también a mano los ejecutaría dos veces.
 */
final class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Clock::class => SystemClock::class,
        TaxCalculator::class => SpanishTaxCalculator::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerImmutabilityObservers();
        $this->registerDocumentRouteBindings();
    }

    /**
     * `{type}` (invoices, credit-notes…) llega como DocumentType, y `{document}`
     * solo encuentra documentos de ese tipo: /credit-notes/{id-de-factura} da 404.
     *
     * Van aquí y no en routes/web.php para que sobrevivan a `route:cache`.
     */
    private function registerDocumentRouteBindings(): void
    {
        Route::bind('type', static fn (string $segment): DocumentType => DocumentType::tryFromRouteSegment($segment) ?? abort(404));

        Route::bind('document', static function (string $id, RoutingRoute $route): Document {
            $type = $route->parameter('type');
            $type = $type instanceof DocumentType ? $type : DocumentType::tryFromRouteSegment((string) $type);

            if ($type === null) {
                abort(404);
            }

            return Document::classFor($type)::query()->findOrFail($id);
        });
    }

    /**
     * Los eventos de Eloquent van por clase concreta: el observador se registra en
     * Document y en cada subclase para que ninguna quede sin proteger.
     */
    private function registerImmutabilityObservers(): void
    {
        foreach ([Document::class, Invoice::class, CreditNote::class, Quote::class] as $model) {
            $model::observe(DocumentObserver::class);
        }

        DocumentLine::observe(DocumentChildObserver::class);
        DocumentTax::observe(DocumentChildObserver::class);
    }
}
