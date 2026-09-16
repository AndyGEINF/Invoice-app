<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * La aplicación no tiene usuarios: el InstallSeeder (emisor vacío y series
     * por defecto) se registra aquí en T034.
     */
    public function run(): void
    {
        //
    }
}
