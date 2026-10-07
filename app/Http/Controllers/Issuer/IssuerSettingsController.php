<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issuer;

use App\Application\Issuer\UpdateIssuer;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\Address;
use App\Domain\Shared\Enums\VatRegime;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssuerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes del emisor: los datos que aparecen en todas las facturas.
 */
final class IssuerSettingsController extends Controller
{
    public function edit(): Response
    {
        $issuer = Issuer::current();
        $address = $issuer->address ?? Address::empty();

        // "settings" y no "issuer": ese nombre ya lo usa la prop compartida del aviso de datos incompletos.
        return Inertia::render('settings/issuer', [
            'settings' => [
                'name' => $issuer->name,
                'company_name' => $issuer->company_name,
                'logo_url' => $issuer->logo_path !== null && $issuer->logo_path !== ''
                    ? Storage::disk(config('invoice.storage.logos_disk'))->url($issuer->logo_path)
                    : null,
                'tax_id' => $issuer->tax_id,
                'address' => $address->toArray(),
                'vat_regime' => $issuer->vat_regime->value,
                'default_irpf_rate' => (string) $issuer->default_irpf_rate,
                'email' => $issuer->email,
                'phone' => $issuer->phone,
                'website' => $issuer->website,
                'invoice_footer' => $issuer->invoice_footer,
                'missing' => $issuer->missing(),
            ],
            'options' => [
                'vat_regimes' => array_map(
                    static fn (VatRegime $regime): array => ['value' => $regime->value, 'label' => $regime->label()],
                    VatRegime::cases(),
                ),
                'irpf_rates' => config('invoice.tax.irpf_rates'),
                'logo_max_kb' => config('invoice.issuer.logo_max_kb'),
            ],
        ]);
    }

    public function update(IssuerRequest $request, UpdateIssuer $updateIssuer): RedirectResponse
    {
        $issuer = $updateIssuer($request->toIssuerData());

        Inertia::flash('success', $issuer->isComplete()
            ? 'Datos guardados. Ya puedes emitir facturas.'
            : 'Datos guardados. Aún faltan datos para poder emitir.');

        return to_route('settings.issuer');
    }
}
