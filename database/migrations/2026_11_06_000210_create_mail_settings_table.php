<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta de correo con la que la aplicación envía los documentos, la que el
 * usuario conecta en Ajustes. Una sola fila, como el emisor. La contraseña se
 * guarda cifrada con la clave de la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('singleton')->default(true)->unique();
            $table->string('provider', 20)->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('test_status', 10)->nullable();
            $table->text('test_error')->nullable();
            $table->timestampTz('tested_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE mail_settings ADD CONSTRAINT mail_settings_singleton_check CHECK (singleton IS TRUE)');
        DB::statement("ALTER TABLE mail_settings ADD CONSTRAINT mail_settings_provider_check CHECK (provider IN ('gmail', 'brevo', 'custom'))");
        DB::statement("ALTER TABLE mail_settings ADD CONSTRAINT mail_settings_test_status_check CHECK (test_status IN ('pending', 'ok', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
