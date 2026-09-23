<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use Carbon\CarbonImmutable;

/**
 * Reloj del dominio.
 *
 * Las reglas que dependen de la fecha (fecha de expedición, vencimiento,
 * caducidad de presupuestos, reinicio anual de series) la piden aquí, de modo
 * que los tests puedan fijar el día sin tocar el reloj del sistema.
 */
interface Clock
{
    public function now(): CarbonImmutable;

    /** Hoy a las 00:00 en la zona horaria de la aplicación. */
    public function today(): CarbonImmutable;
}
