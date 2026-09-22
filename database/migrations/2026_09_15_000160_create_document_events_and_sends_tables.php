<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial del documento y registro de cada envío por email.
 *
 * El historial explica qué pasó y cuándo. Los envíos se guardan uno a uno: un
 * fallo se puede reintentar sin cambiar el estado del documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('event', 30);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });

        DB::statement("ALTER TABLE document_events ADD CONSTRAINT document_events_event_check CHECK (event IN (
            'created', 'updated', 'deleted', 'issued', 'sent', 'send_failed',
            'marked_paid', 'unmarked_paid', 'rectified', 'converted',
            'quote_sent', 'quote_accepted', 'quote_rejected'
        ))");

        Schema::create('document_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->jsonb('to');
            $table->jsonb('cc')->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->string('status', 10)->default('queued');
            $table->text('error')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestampTz('queued_at')->useCurrent();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['document_id', 'queued_at']);
        });

        DB::statement("ALTER TABLE document_sends ADD CONSTRAINT document_sends_status_check CHECK (status IN ('queued', 'sent', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sends');
        Schema::dropIfExists('document_events');
    }
};
