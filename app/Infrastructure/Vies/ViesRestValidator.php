<?php

declare(strict_types=1);

namespace App\Infrastructure\Vies;

use App\Domain\Customers\Contracts\VatNumberValidator;
use App\Domain\Customers\Contracts\ViesResult;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\TaxId;
use Illuminate\Http\Client\Factory as HttpClient;
use Throwable;

/**
 * Consulta la API REST pública de VIES de la Comisión Europea:
 * `GET {endpoint}/ms/{país}/vat/{número}`.
 *
 * Cualquier fallo (red, timeout, error del estado miembro) es "no disponible",
 * nunca "no válido": VIES cae a menudo y no debe marcar como malo un número
 * correcto.
 */
final readonly class ViesRestValidator implements VatNumberValidator
{
    /** Nombre que VIES devuelve cuando el estado miembro no lo comparte. */
    private const string HIDDEN_NAME = '---';

    public function __construct(
        private HttpClient $http,
        private Clock $clock,
        private string $endpoint,
        private int $timeoutSeconds,
    ) {}

    public function validate(TaxId $taxId): ViesResult
    {
        $now = $this->clock->now();

        if (! $taxId->isEuVat()) {
            return ViesResult::invalid($now);
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->get(sprintf('%s/ms/%s/vat/%s', rtrim($this->endpoint, '/'), $taxId->country, rawurlencode($taxId->nationalNumber())));
        } catch (Throwable) {
            return ViesResult::unavailable($now);
        }

        if (! $response->successful() || ! is_bool($response->json('isValid'))) {
            return ViesResult::unavailable($now);
        }

        // Con isValid=false, userError dice si es un número malo (INVALID) o un fallo del servicio.
        if ($response->json('isValid') === false) {
            return $response->json('userError') === 'INVALID'
                ? ViesResult::invalid($now)
                : ViesResult::unavailable($now);
        }

        $name = trim((string) $response->json('name'));

        return ViesResult::valid($now, $name !== '' && $name !== self::HIDDEN_NAME ? $name : null);
    }
}
