<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Instalación mínima: emisor vacío y series por defecto.
     *
     * Para datos de demostración: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call(InstallSeeder::class);
    }
}
