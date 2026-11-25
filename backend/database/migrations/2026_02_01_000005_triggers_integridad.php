<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Capa de persistencia (PostgreSQL): reglas que la propia base garantiza aunque se escriba fuera de la API.
 *  1. La bitácora de accesos es de solo escritura: ningún UPDATE puede alterar un registro de auditoría.
 *  2. Contador de visitas: toda modificación del contador deja actualizada la fecha de la última visita.
 *  3. Al pagarse la última cuota pendiente, el plan de pagos pasa a COMPLETADO.
 * Solo se crean en PostgreSQL; con otros motores (pruebas con SQLite) la aplicación aplica las mismas reglas desde el servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fn_bitacora_solo_escritura() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'La bitácora de accesos es de solo escritura: no se permite modificar registros de auditoría.';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_bitacora_solo_escritura ON bitacora_acceso;
CREATE TRIGGER tg_bitacora_solo_escritura BEFORE UPDATE ON bitacora_acceso
    FOR EACH ROW EXECUTE FUNCTION fn_bitacora_solo_escritura();

CREATE OR REPLACE FUNCTION fn_visita_ultima_fecha() RETURNS trigger AS $$
BEGIN
    IF NEW.contador IS DISTINCT FROM OLD.contador THEN
        NEW.ultima_visita := now();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_visita_ultima_fecha ON visita_pagina;
CREATE TRIGGER tg_visita_ultima_fecha BEFORE UPDATE ON visita_pagina
    FOR EACH ROW EXECUTE FUNCTION fn_visita_ultima_fecha();

CREATE OR REPLACE FUNCTION fn_plan_completado() RETURNS trigger AS $$
BEGIN
    IF NEW.estado = 'PAGADA' AND NOT EXISTS (SELECT 1 FROM cuota WHERE id_plan = NEW.id_plan AND estado = 'PENDIENTE') THEN
        UPDATE plan_pago SET estado = 'COMPLETADO', updated_at = now() WHERE id_plan = NEW.id_plan AND estado = 'ACTIVO';
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_plan_completado ON cuota;
CREATE TRIGGER tg_plan_completado AFTER UPDATE OF estado ON cuota
    FOR EACH ROW EXECUTE FUNCTION fn_plan_completado();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS tg_plan_completado ON cuota;
DROP TRIGGER IF EXISTS tg_visita_ultima_fecha ON visita_pagina;
DROP TRIGGER IF EXISTS tg_bitacora_solo_escritura ON bitacora_acceso;
DROP FUNCTION IF EXISTS fn_plan_completado();
DROP FUNCTION IF EXISTS fn_visita_ultima_fecha();
DROP FUNCTION IF EXISTS fn_bitacora_solo_escritura();
SQL);
    }
};
