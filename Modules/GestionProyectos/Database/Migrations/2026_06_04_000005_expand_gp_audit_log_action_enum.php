<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende el enum de gp_audit_log.action para cubrir borrados en masa.
 * ALTER TABLE MODIFY en MySQL es online para cambios de enum que sólo agregan valores.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE gp_audit_log MODIFY action
             ENUM('created','updated','deleted','restored','bulk_deleted') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE gp_audit_log MODIFY action
             ENUM('created','updated','deleted','restored') NOT NULL"
        );
    }
};
