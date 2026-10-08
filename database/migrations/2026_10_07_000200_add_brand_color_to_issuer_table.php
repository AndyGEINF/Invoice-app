<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Color de marca del emisor: tiñe la cabecera, los títulos y el total del PDF.
 * Siempre `#RRGGBB`; por defecto, el azul de la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuer', function (Blueprint $table) {
            $table->char('brand_color', 7)->default('#2563eb')->after('logo_path');
        });

        DB::statement("ALTER TABLE issuer ADD CONSTRAINT issuer_brand_color_check CHECK (brand_color ~ '^#[0-9a-fA-F]{6}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE issuer DROP CONSTRAINT IF EXISTS issuer_brand_color_check');

        Schema::table('issuer', function (Blueprint $table) {
            $table->dropColumn('brand_color');
        });
    }
};
