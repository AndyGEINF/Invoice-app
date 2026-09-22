<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas del documento y desglose de impuestos.
 *
 * Cada línea guarda su propia descripción, precio y tipos: el producto es solo
 * una referencia (decisión D4). El desglose agrupa por tipo impositivo y es lo
 * que se valida fiscalmente, no la suma de las líneas (decisión D3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->smallInteger('position');

            // Referencia al catálogo: si el producto desaparece, la línea sigue intacta.
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->text('description');
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20)->default('ud');
            $table->bigInteger('unit_price');
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->decimal('vat_rate', 5, 2);
            $table->decimal('surcharge_rate', 5, 2)->default(0);
            $table->boolean('irpf_applies')->default(false);
            $table->string('exemption_code', 5)->nullable();

            // Base de la línea tras descuentos, solo informativa.
            $table->bigInteger('line_base')->default(0);

            $table->timestampsTz();

            $table->unique(['document_id', 'position']);
        });

        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_quantity_check CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_discount_check CHECK (discount_percent >= 0 AND discount_percent <= 100)');
        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_vat_rate_check CHECK (vat_rate >= 0 AND vat_rate <= 100)');
        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_surcharge_rate_check CHECK (surcharge_rate >= 0 AND surcharge_rate <= 100)');
        DB::statement("ALTER TABLE document_lines ADD CONSTRAINT document_lines_exemption_code_check CHECK (exemption_code IS NULL OR exemption_code IN ('E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'NS'))");

        Schema::create('document_taxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('tax_type', 5);
            $table->decimal('rate', 5, 2);
            $table->bigInteger('base');
            $table->bigInteger('amount');
            $table->string('exemption_code', 5)->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE document_taxes ADD CONSTRAINT document_taxes_type_check CHECK (tax_type IN ('IVA', 'RE', 'IRPF'))");
        DB::statement('ALTER TABLE document_taxes ADD CONSTRAINT document_taxes_rate_check CHECK (rate >= 0 AND rate <= 100)');
        DB::statement('ALTER TABLE document_taxes ADD CONSTRAINT document_taxes_amount_check CHECK (amount >= 0)');
        DB::statement("ALTER TABLE document_taxes ADD CONSTRAINT document_taxes_exemption_code_check CHECK (exemption_code IS NULL OR exemption_code IN ('E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'NS'))");

        // Un solo grupo por combinación de tipo impositivo y causa de exención.
        DB::statement("CREATE UNIQUE INDEX document_taxes_group_unique ON document_taxes (document_id, tax_type, rate, COALESCE(exemption_code, ''))");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_taxes');
        Schema::dropIfExists('document_lines');
    }
};
