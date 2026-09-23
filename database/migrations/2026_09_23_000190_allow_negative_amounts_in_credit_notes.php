<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Importes negativos solo en rectificativas.
 *
 * Una rectificativa por diferencias que devuelve dinero al cliente (un abono)
 * necesita líneas con cantidad negativa, y su desglose tiene bases y cuotas
 * negativas. Facturas y presupuestos siguen sin admitir negativos.
 *
 * El precio unitario es siempre positivo: el signo lo lleva la cantidad
 * ("-2 horas" devueltas), como en la mayoría de programas de facturación.
 *
 * Como una restricción CHECK no puede mirar la tabla del documento, la regla
 * pasa a un trigger que consulta el tipo del documento padre.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE document_lines DROP CONSTRAINT document_lines_quantity_check');
        DB::statement('ALTER TABLE document_taxes DROP CONSTRAINT document_taxes_amount_check');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION document_amounts_sign_check() RETURNS trigger AS $$
            DECLARE
                parent_type text;
                is_negative boolean;
            BEGIN
                SELECT type INTO parent_type FROM documents WHERE id = NEW.document_id;

                IF TG_TABLE_NAME = 'document_lines' THEN
                    is_negative := NEW.quantity < 0;
                ELSE
                    is_negative := NEW.base < 0 OR NEW.amount < 0;
                END IF;

                IF is_negative AND parent_type IS DISTINCT FROM 'credit_note' THEN
                    RAISE EXCEPTION
                        'Solo las rectificativas admiten importes negativos (tabla %, documento de tipo %)',
                        TG_TABLE_NAME, COALESCE(parent_type, 'desconocido');
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER document_lines_sign_check
                BEFORE INSERT OR UPDATE ON document_lines
                FOR EACH ROW EXECUTE FUNCTION document_amounts_sign_check();

            CREATE TRIGGER document_taxes_sign_check
                BEFORE INSERT OR UPDATE ON document_taxes
                FOR EACH ROW EXECUTE FUNCTION document_amounts_sign_check();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS document_taxes_sign_check ON document_taxes');
        DB::unprepared('DROP TRIGGER IF EXISTS document_lines_sign_check ON document_lines');
        DB::unprepared('DROP FUNCTION IF EXISTS document_amounts_sign_check()');

        DB::statement('ALTER TABLE document_taxes ADD CONSTRAINT document_taxes_amount_check CHECK (amount >= 0)');
        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_quantity_check CHECK (quantity >= 0)');
    }
};
