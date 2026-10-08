<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Customers\Contracts\VatNumberValidator;
use App\Domain\Customers\Customer;
use App\Domain\Shared\Contracts\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Confirma en VIES el número de IVA intracomunitario de un cliente.
 *
 * Idempotente: no consulta si el cliente ya no tiene número europeo o si se
 * validó hace menos de `invoice.vies.revalidate_after_days` (salvo `$force`, la
 * validación pedida a mano). Solo una respuesta válida fija `vies_validated_at`;
 * "no válido" y "no disponible" lo dejan como estaba, y nunca bloquean emitir.
 * Si VIES no responde, se reintenta más tarde.
 */
final class ValidateVatNumberJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public function __construct(
        public readonly string $customerId,
        public readonly bool $force = false,
    ) {
        $this->tries = (int) config('invoice.vies.tries');
        $this->afterCommit();
    }

    /** Una sola validación pendiente por cliente. */
    public function uniqueId(): string
    {
        return $this->customerId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return config('invoice.vies.backoff_seconds');
    }

    public function handle(VatNumberValidator $validator, Clock $clock): void
    {
        $customer = Customer::query()->find($this->customerId);
        $taxId = $customer?->taxId();

        if ($customer === null || $taxId === null || ! $taxId->isEuVat()) {
            return;
        }

        $recentLimit = $clock->now()->subDays((int) config('invoice.vies.revalidate_after_days'));

        if (! $this->force && $customer->vies_validated_at !== null && $customer->vies_validated_at->greaterThan($recentLimit)) {
            return;
        }

        $result = $validator->validate($taxId);

        if ($result->isConfirmed()) {
            $customer->forceFill(['vies_validated_at' => $result->checkedAt])->save();

            return;
        }

        if (! $result->available && $this->attempts() < $this->tries) {
            $this->release($this->retryDelay());
        }
    }

    private function retryDelay(): int
    {
        $delays = $this->backoff();

        return $delays[min($this->attempts(), count($delays)) - 1] ?? 0;
    }
}
