<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presupuestos, facturas y rectificativas en una sola tabla (decisión D1).
 *
 * Los importes van en céntimos; el número y las fechas se rellenan al emitir, y
 * los snapshots congelan emisor y cliente en ese momento. Las columnas fiscales
 * (`invoice_type`, `rectifies_id`) existen desde ahora aunque VeriFactu llegue
 * en una fase posterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);
            $table->string('status', 20);

            // Numeración: solo existe cuando el documento se emite o se envía.
            $table->foreignUuid('series_id')->nullable()->constrained('series')->restrictOnDelete();
            $table->smallInteger('fiscal_year')->nullable();
            $table->integer('number')->nullable();
            $table->string('full_number', 40)->nullable();

            // Destinatario y copias congeladas.
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->jsonb('issuer_snapshot')->nullable();
            $table->jsonb('customer_snapshot')->nullable();

            // Fechas.
            $table->date('issue_date')->nullable();
            $table->date('operation_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('valid_until')->nullable();

            // Condiciones e importes.
            $table->char('currency', 3)->default('EUR');
            $table->decimal('global_discount_percent', 5, 2)->default(0);
            $table->decimal('irpf_rate', 5, 2)->default(0);
            $table->bigInteger('taxable_base')->default(0);
            $table->bigInteger('vat_total')->default(0);
            $table->bigInteger('surcharge_total')->default(0);
            $table->bigInteger('irpf_total')->default(0);
            $table->bigInteger('total')->default(0);

            // Marcador visual de cobro: la aplicación no gestiona pagos.
            $table->date('paid_at')->nullable();
            $table->string('paid_note', 200)->nullable();

            // Clasificación fiscal y relaciones entre documentos. Las claves
            // foráneas contra la propia tabla se añaden abajo, cuando ya existe.
            $table->string('invoice_type', 5)->nullable();
            $table->uuid('converted_from_id')->nullable();
            $table->uuid('rectifies_id')->nullable();
            $table->char('rectification_type', 1)->nullable();
            $table->text('rectification_reason')->nullable();

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Hitos del ciclo de vida.
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('rectified_at')->nullable();

            // PDF conservado del documento emitido.
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestampTz('pdf_generated_at')->nullable();

            $table->timestampsTz();

            $table->index(['type', 'status']);
            $table->index('customer_id');
            $table->index('issue_date');
        });

        // Presupuesto de origen y factura rectificada: se apuntan a documents.
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('converted_from_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreign('rectifies_id')->references('id')->on('documents')->restrictOnDelete();
        });

        // Numeración correlativa: red de seguridad frente a dos emisiones simultáneas.
        DB::statement('CREATE UNIQUE INDEX documents_series_number_unique ON documents (series_id, fiscal_year, number) WHERE number IS NOT NULL');

        // Facturas pendientes de cobro: el listado por vencimiento es el más consultado.
        DB::statement('CREATE INDEX documents_due_date_unpaid_index ON documents (due_date) WHERE paid_at IS NULL');

        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (type IN ('quote', 'invoice', 'credit_note'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('draft', 'issued', 'sent', 'rectified', 'accepted', 'rejected', 'converted'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_invoice_type_check CHECK (invoice_type IS NULL OR invoice_type IN ('F1', 'F2', 'R1', 'R4', 'R5'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_rectification_type_check CHECK (rectification_type IS NULL OR rectification_type IN ('S', 'I'))");
        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_discount_check CHECK (global_discount_percent >= 0 AND global_discount_percent <= 100)');
        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_irpf_rate_check CHECK (irpf_rate >= 0 AND irpf_rate <= 100)');

        // El total siempre cuadra con el desglose.
        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_total_check CHECK (total = taxable_base + vat_total + surcharge_total - irpf_total)');

        // Una factura emitida tiene número, fecha y snapshot del emisor.
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_issued_needs_data_check CHECK (
            type = 'quote' OR status = 'draft'
            OR (number IS NOT NULL AND issue_date IS NOT NULL AND issuer_snapshot IS NOT NULL)
        )");

        // Una rectificativa siempre apunta a la factura que corrige y dice cómo.
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_rectification_needs_origin_check CHECK (
            type <> 'credit_note' OR (rectifies_id IS NOT NULL AND rectification_type IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
