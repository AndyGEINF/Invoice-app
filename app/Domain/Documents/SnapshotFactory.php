<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Customers\Customer;
use App\Domain\Issuer\Issuer;

/**
 * Copias congeladas de emisor y cliente que se guardan en el documento al
 * emitirlo (decisión D4).
 *
 * Reimprimir una factura del año pasado debe mostrar la dirección y el
 * logotipo de entonces, aunque hayan cambiado después.
 */
final class SnapshotFactory
{
    /** @return array<string, mixed> */
    public function issuer(Issuer $issuer): array
    {
        return $issuer->toSnapshot();
    }

    /** @return array<string, mixed> */
    public function customer(Customer $customer): array
    {
        return $customer->toSnapshot();
    }

    /**
     * Congela ambos en el documento. Solo se llama al emitir (o al enviar un
     * presupuesto), nunca al crear el borrador.
     */
    public function freezeInto(Document $document, Issuer $issuer, ?Customer $customer): void
    {
        $document->issuer_snapshot = $this->issuer($issuer);
        $document->customer_snapshot = $customer !== null ? $this->customer($customer) : null;
    }
}
