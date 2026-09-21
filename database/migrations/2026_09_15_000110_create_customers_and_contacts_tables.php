<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes y sus contactos.
 *
 * Un cliente sin identificador fiscal solo puede recibir facturas
 * simplificadas. Los clientes con documentos emitidos no se borran: se
 * archivan, porque la factura conserva sus datos congelados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();

            $table->string('tax_id', 20)->nullable();
            $table->string('tax_id_type', 10)->nullable();
            $table->timestampTz('vies_validated_at')->nullable();

            $table->jsonb('billing_address');
            $table->string('email')->nullable();
            $table->smallInteger('payment_terms_days')->default(30);

            $table->boolean('irpf_applies')->default(false);
            $table->boolean('surcharge_applies')->default(false);
            $table->decimal('default_vat_rate', 5, 2)->nullable();

            $table->text('notes')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index('legal_name');
            $table->index('tax_id');
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_kind_check CHECK (kind IN ('individual', 'business'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_tax_id_type_check CHECK (tax_id_type IS NULL OR tax_id_type IN ('NIF', 'NIE', 'CIF', 'VAT_EU', 'OTHER'))");
        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_payment_terms_check CHECK (payment_terms_days >= 0 AND payment_terms_days <= 365)');
        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_vat_rate_check CHECK (default_vat_rate IS NULL OR (default_vat_rate >= 0 AND default_vat_rate <= 100))');
        // Una empresa siempre tiene identificador fiscal; un particular puede no tenerlo.
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_business_needs_tax_id_check CHECK (kind <> 'business' OR tax_id IS NOT NULL)");

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customers');
    }
};
