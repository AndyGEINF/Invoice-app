<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Series de numeración.
 *
 * Cada emisión bloquea la fila de su serie e incrementa el contador, de modo
 * que la numeración sea correlativa y sin huecos aunque se emita dos veces a la
 * vez (decisión D5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type', 20);
            $table->string('code', 10)->unique();
            $table->string('prefix', 10);
            $table->smallInteger('padding')->default(4);
            $table->integer('next_number')->default(1);
            $table->boolean('resets_yearly')->default(true);
            $table->smallInteger('year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('document_type');
        });

        DB::statement("ALTER TABLE series ADD CONSTRAINT series_document_type_check CHECK (document_type IN ('quote', 'invoice', 'credit_note'))");
        DB::statement('ALTER TABLE series ADD CONSTRAINT series_padding_check CHECK (padding >= 1 AND padding <= 8)');
        DB::statement('ALTER TABLE series ADD CONSTRAINT series_next_number_check CHECK (next_number >= 1)');
        // Una sola serie por defecto para cada tipo de documento.
        DB::statement('CREATE UNIQUE INDEX series_default_per_type_unique ON series (document_type) WHERE is_default');
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
