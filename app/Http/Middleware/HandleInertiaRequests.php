<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Issuer\Issuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

/**
 * Datos que reciben todas las páginas.
 *
 * - `issuer`: nombre, empresa, logotipo y qué falta para poder emitir. El layout
 *   muestra un aviso permanente mientras el emisor no esté completo.
 * - `invoiceConfig`: tipos de IVA, recargo, IRPF y causas de exención. No cambian
 *   durante la sesión, así que se envían una sola vez.
 *
 * Los mensajes de éxito o error usan el flash de Inertia (`Inertia::flash()`).
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'issuer' => fn (): array => $this->issuerSummary(),
        ];
    }

    /** @return array<string, callable> */
    public function shareOnce(Request $request): array
    {
        return [
            'invoiceConfig' => fn (): array => [
                'vatRates' => config('invoice.tax.vat_rates'),
                'surchargeRates' => config('invoice.tax.surcharge_rates'),
                'surchargeByVatRate' => config('invoice.tax.surcharge_by_vat_rate'),
                'irpfRates' => config('invoice.tax.irpf_rates'),
                'exemptionCodes' => config('invoice.tax.exemption_codes'),
                'defaultCurrency' => 'EUR',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function issuerSummary(): array
    {
        $issuer = Issuer::current();
        $logoPath = $issuer->logo_path;
        // missing() valida NIF y dirección: se calcula una vez y se reutiliza.
        $missing = $issuer->missing();

        return [
            'name' => $issuer->name,
            'companyName' => $issuer->company_name,
            'legalName' => $issuer->legalName(),
            'logoUrl' => $logoPath !== null && $logoPath !== ''
                ? Storage::disk(config('invoice.storage.logos_disk'))->url($logoPath)
                : null,
            'isComplete' => $missing === [],
            'missing' => $missing,
        ];
    }
}
