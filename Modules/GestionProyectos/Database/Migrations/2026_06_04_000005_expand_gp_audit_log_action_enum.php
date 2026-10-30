<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende el enum de gp_audit_log.action para cubrir borrados en masa.
 * ALTER TABLE MODIFY en MySQL es online para cambios de enum que sólo agregan valores.
 *
 * En PostgreSQL, Schema::enum() se implementa como VARCHAR + CHECK constraint
 * (no existe un tipo ENUM nativo editable con MODIFY), así que ahí simplemente
 * se elimina ese CHECK: la validación de valores permitidos ya ocurre a nivel
 * de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE gp_audit_log DROP CONSTRAINT IF EXISTS gp_audit_log_action_check');
            DB::statement('ALTER TABLE gp_audit_log ALTER COLUMN action TYPE VARCHAR(20)');

            return;
        }

        DB::statement(
            "ALTER TABLE gp_audit_log MODIFY action
             ENUM('created','updated','deleted','restored','bulk_deleted') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // El CHECK constraint original ya fue eliminado arriba; no se reconstruye.
            return;
        }

        DB::statement(
            "ALTER TABLE gp_audit_log MODIFY action
             ENUM('created','updated','deleted','restored') NOT NULL"
        );
    }
};
