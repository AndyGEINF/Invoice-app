<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de facturación encadenado (VeriFactu).
 *
 * En la fase 1 no se escribe nada aquí: la tabla existe desde el principio para
 * que la fase fiscal no tenga que migrar facturas ya emitidas, que legalmente
 * son intocables.
 *
 * Es append-only: solo INSERT. Un trigger rechaza cualquier UPDATE o DELETE,
 * porque alterar un registro rompería la cadena de huellas (decisión D7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->restrictOnDelete();

            $table->string('record_type', 20);
            $table->string('invoice_type', 5);
            $table->string('issuer_tax_id', 20);
            $table->string('series_number', 40);
            $table->date('issue_date');
            $table->bigInteger('total_amount');
            $table->bigInteger('tax_amount');

            // Cadena de huellas.
            $table->uuid('previous_record_id')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestampTz('hashed_at');

            // Declaración responsable del software.
            $table->string('software_id', 50);
            $table->string('software_version', 20);
            $table->string('installation_number', 50);

            // Envío a la AEAT.
            $table->text('qr_payload')->nullable();
            $table->text('xml_payload')->nullable();
            $table->string('aeat_status', 20)->nullable();
            $table->string('aeat_csv', 50)->nullable();
            $table->jsonb('aeat_response')->nullable();
            $table->string('idempotency_key', 100)->unique();

            $table->timestampTz('created_at')->useCurrent();

            $table->index('document_id');
            $table->index('hash');
        });

        DB::statement('ALTER TABLE billing_records ADD CONSTRAINT billing_records_previous_foreign FOREIGN KEY (previous_record_id) REFERENCES billing_records (id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE billing_records ADD CONSTRAINT billing_records_type_check CHECK (record_type IN ('alta', 'anulacion', 'subsanacion'))");

        // Append-only: la garantía vive en la base de datos, no solo en el código.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION billing_records_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'billing_records es append-only: solo se permite INSERT (operacion %)', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER billing_records_append_only
                BEFORE UPDATE OR DELETE ON billing_records
                FOR EACH ROW EXECUTE FUNCTION billing_records_append_only();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS billing_records_append_only ON billing_records');
        DB::unprepared('DROP FUNCTION IF EXISTS billing_records_append_only()');
        Schema::dropIfExists('billing_records');
    }
};
