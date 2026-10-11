<?php

declare(strict_types=1);

namespace App\Http\Queries;

use Illuminate\Http\Request;

/**
 * Filas por página de los listados (`?per_page=`). Solo se admiten las opciones
 * del selector; cualquier otro valor vuelve al de por defecto.
 */
final readonly class PerPage
{
    /** Las mismas que ofrece el selector de `resources/js/components/Pagination.tsx`. */
    public const array OPTIONS = [10, 25, 50];

    public const int DEFAULT = 10;

    public static function fromRequest(Request $request): int
    {
        $value = $request->integer('per_page', self::DEFAULT);

        return in_array($value, self::OPTIONS, true) ? $value : self::DEFAULT;
    }
}
