<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Precio unitario escrito en un formulario (producto o línea de documento):
 * euros con dos decimales como mucho. Se admiten ceros detrás ("10.000",
 * "33.330"), que es como lo devuelve el servidor al editar, pero no una tercera
 * cifra significativa ("33.333").
 *
 * El dominio sigue guardando milésimas (`UnitPrice`); esto solo limita lo que
 * se puede escribir.
 */
final readonly class UnitPriceRule
{
    public const string PATTERN = '/^\d{1,10}(\.\d{1,2}0*)?$/';

    public const string MESSAGE = 'El precio debe ser un importe positivo con dos decimales como mucho.';
}
