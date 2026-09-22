<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inmutabilidad de lo emitido (decisión D6).
 *
 * Una factura o rectificativa que ya no es borrador no se puede modificar ni
 * borrar. La barrera está en la base de datos para que sobreviva a SQL crudo, a
 * tinker y a cualquier migración de datos; el modelo añade la suya con mensajes
 * para la interfaz.
 *
 * Solo pueden cambiar: el estado (hacia enviada o rectificada), el marcador de
 * cobro, la fecha de envío, la de rectificación, los datos del PDF y las notas
 * internas, que no salen en la factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION documents_immutable_when_issued() RETURNS trigger AS $$
            BEGIN
                -- Los presupuestos y los borradores se editan y se borran con libertad.
                IF OLD.type NOT IN ('invoice', 'credit_note') OR OLD.status = 'draft' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION
                        'La factura % esta emitida y no se puede borrar: corrigela con una rectificativa',
                        COALESCE(OLD.full_number, OLD.id::text);
                END IF;

                -- Ningun dato fiscal puede cambiar despues de emitir.
                IF (
                    NEW.type, NEW.series_id, NEW.fiscal_year, NEW.number, NEW.full_number,
                    NEW.customer_id, NEW.issuer_snapshot, NEW.customer_snapshot,
                    NEW.issue_date, NEW.operation_date, NEW.due_date, NEW.valid_until,
                    NEW.currency, NEW.global_discount_percent, NEW.irpf_rate,
                    NEW.taxable_base, NEW.vat_total, NEW.surcharge_total, NEW.irpf_total, NEW.total,
                    NEW.invoice_type, NEW.converted_from_id, NEW.rectifies_id,
                    NEW.rectification_type, NEW.rectification_reason, NEW.notes, NEW.issued_at
                ) IS DISTINCT FROM (
                    OLD.type, OLD.series_id, OLD.fiscal_year, OLD.number, OLD.full_number,
                    OLD.customer_id, OLD.issuer_snapshot, OLD.customer_snapshot,
                    OLD.issue_date, OLD.operation_date, OLD.due_date, OLD.valid_until,
                    OLD.currency, OLD.global_discount_percent, OLD.irpf_rate,
                    OLD.taxable_base, OLD.vat_total, OLD.surcharge_total, OLD.irpf_total, OLD.total,
                    OLD.invoice_type, OLD.converted_from_id, OLD.rectifies_id,
                    OLD.rectification_type, OLD.rectification_reason, OLD.notes, OLD.issued_at
                ) THEN
                    RAISE EXCEPTION
                        'La factura % esta emitida y sus datos fiscales son inmutables: corrigela con una rectificativa',
                        COALESCE(OLD.full_number, OLD.id::text);
                END IF;

                -- El estado solo avanza: emitida -> enviada -> rectificada.
                IF NEW.status IS DISTINCT FROM OLD.status AND NOT (
                    (OLD.status = 'issued' AND NEW.status IN ('sent', 'rectified'))
                    OR (OLD.status = 'sent' AND NEW.status = 'rectified')
                ) THEN
                    RAISE EXCEPTION
                        'Transicion de estado no permitida en la factura %: de % a %',
                        COALESCE(OLD.full_number, OLD.id::text), OLD.status, NEW.status;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER documents_immutable_when_issued
                BEFORE UPDATE OR DELETE ON documents
                FOR EACH ROW EXECUTE FUNCTION documents_immutable_when_issued();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION document_children_immutable() RETURNS trigger AS $$
            DECLARE
                parent_document documents%ROWTYPE;
                parent_id uuid;
            BEGIN
                parent_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.document_id ELSE NEW.document_id END;

                SELECT * INTO parent_document FROM documents WHERE id = parent_id;

                -- Si el documento ya no existe, esto es el borrado en cascada de un borrador.
                IF NOT FOUND THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;

                IF parent_document.type IN ('invoice', 'credit_note') AND parent_document.status <> 'draft' THEN
                    RAISE EXCEPTION
                        'La factura % esta emitida: sus lineas e impuestos son inmutables (operacion % sobre %)',
                        COALESCE(parent_document.full_number, parent_document.id::text), TG_OP, TG_TABLE_NAME;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER document_lines_immutable_when_issued
                BEFORE INSERT OR UPDATE OR DELETE ON document_lines
                FOR EACH ROW EXECUTE FUNCTION document_children_immutable();

            CREATE TRIGGER document_taxes_immutable_when_issued
                BEFORE INSERT OR UPDATE OR DELETE ON document_taxes
                FOR EACH ROW EXECUTE FUNCTION document_children_immutable();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS document_taxes_immutable_when_issued ON document_taxes');
        DB::unprepared('DROP TRIGGER IF EXISTS document_lines_immutable_when_issued ON document_lines');
        DB::unprepared('DROP FUNCTION IF EXISTS document_children_immutable()');
        DB::unprepared('DROP TRIGGER IF EXISTS documents_immutable_when_issued ON documents');
        DB::unprepared('DROP FUNCTION IF EXISTS documents_immutable_when_issued()');
    }
};
