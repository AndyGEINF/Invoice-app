<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

/**
 * Almacén de los PDF de documentos emitidos.
 *
 * El PDF de una factura emitida se guarda una sola vez y se conserva: volver a
 * descargarlo devuelve siempre el mismo contenido.
 */
interface DocumentStorage
{
    public function put(string $path, string $bytes): void;

    public function get(string $path): string;

    public function exists(string $path): bool;
}
