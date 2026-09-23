<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Series;
use App\Domain\Issuer\Issuer;
use App\Domain\Shared\DocumentNumber;
use Illuminate\Database\Seeder;

/**
 * Deja la aplicación lista para su primer uso: el emisor vacío (que el usuario
 * completa desde Ajustes) y una serie por defecto de facturas, rectificativas y
 * presupuestos.
 *
 * Es idempotente: se puede ejecutar en cada despliegue sin duplicar nada ni
 * tocar lo que el usuario ya haya configurado.
 */
final class InstallSeeder extends Seeder
{
    public function run(): void
    {
        Issuer::current();

        /** @var list<array{document_type: string, code: string, prefix: string}> $defaults */
        $defaults = config('invoice.series.defaults');
        $padding = (int) config('invoice.series.default_padding', DocumentNumber::DEFAULT_PADDING);

        foreach ($defaults as $default) {
            $type = DocumentType::from($default['document_type']);

            // Si el usuario ya tiene una serie por defecto para este tipo, se respeta.
            if (Series::defaultFor($type) !== null) {
                continue;
            }

            Series::query()->firstOrCreate(
                ['code' => $default['code']],
                [
                    'document_type' => $type,
                    'prefix' => $default['prefix'],
                    'padding' => $padding,
                    'next_number' => DocumentNumber::FIRST_NUMBER,
                    'resets_yearly' => true,
                    'is_default' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
