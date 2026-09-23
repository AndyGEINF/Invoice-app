<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emisor único de la aplicación.
 *
 * Una sola fila, garantizada por la columna `singleton` con índice único. Guarda
 * lo que identifica las facturas (nombre, empresa y logotipo) y los datos
 * fiscales. Sin ellos no se puede emitir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuer', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('singleton')->default(true)->unique();

            // Identidad visible en la factura.
            $table->string('name', 200)->nullable();
            $table->string('company_name', 200)->nullable();
            $table->string('logo_path')->nullable();

            // Datos fiscales.
            $table->string('tax_id', 20)->nullable();
            $table->string('tax_id_type', 10)->nullable();
            $table->jsonb('address')->nullable();
            $table->string('vat_regime', 20)->default('general');
            $table->decimal('default_irpf_rate', 5, 2)->default(0);
            $table->char('default_currency', 3)->default('EUR');

            // Contacto y pie legal.
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->text('invoice_footer')->nullable();

            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE issuer ADD CONSTRAINT issuer_singleton_check CHECK (singleton IS TRUE)');
        DB::statement("ALTER TABLE issuer ADD CONSTRAINT issuer_tax_id_type_check CHECK (tax_id_type IS NULL OR tax_id_type IN ('NIF', 'NIE', 'CIF'))");
        DB::statement("ALTER TABLE issuer ADD CONSTRAINT issuer_vat_regime_check CHECK (vat_regime IN ('general', 'surcharge', 'exempt'))");
        DB::statement('ALTER TABLE issuer ADD CONSTRAINT issuer_irpf_rate_check CHECK (default_irpf_rate >= 0 AND default_irpf_rate <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('issuer');
    }
};
