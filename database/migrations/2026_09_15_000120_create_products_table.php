<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de productos y servicios.
 *
 * El precio va en milésimas porque un precio por hora puede ser 33,333 €. Al
 * crear una línea se copian descripción, precio y tipo de IVA: el catálogo es
 * solo el punto de partida (decisión D4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 50)->nullable();
            $table->string('type', 10);
            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->bigInteger('unit_price');
            $table->string('unit', 20)->default('ud');

            $table->decimal('vat_rate', 5, 2);
            $table->string('exemption_code', 5)->nullable();
            $table->boolean('irpf_applicable')->default(false);

            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index('name');
        });

        DB::statement('CREATE UNIQUE INDEX products_sku_unique ON products (sku) WHERE sku IS NOT NULL');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_type_check CHECK (type IN ('product', 'service'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_vat_rate_check CHECK (vat_rate >= 0 AND vat_rate <= 100)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_exemption_code_check CHECK (exemption_code IS NULL OR exemption_code IN ('E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'NS'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
