<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * La demo con mucho volumen, para ver la paginación y los listados llenos:
 * 90 clientes, 180 productos, 75 facturas emitidas (cobradas, pendientes y
 * vencidas) y 45 presupuestos.
 *
 * Solo para desarrollo: `php artisan db:seed --class=LargeDemoSeeder`.
 */
final class LargeDemoSeeder extends Seeder
{
    private const int SCALE = 15;

    public function run(): void
    {
        $this->callWith(DemoSeeder::class, ['scale' => self::SCALE]);
    }
}
