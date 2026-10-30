<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía gp_projects.icon de TEXT (~64 KB) a MEDIUMTEXT (~16 MB) en MySQL.
 * El ícono se guarda como dataURL base64; un logo de 200 KB ≈ 273 KB de base64,
 * que no cabía en TEXT de MySQL (causaba "Data too long for column 'icon'").
 *
 * En PostgreSQL, TEXT ya no tiene límite práctico de tamaño (hasta 1 GB por valor),
 * así que este cambio no aplica ahí: se omite para evitar un error de sintaxis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE gp_projects MODIFY icon MEDIUMTEXT NULL');
        }
    }

    public function down(): void
    {
        // Volver a TEXT podría truncar íconos grandes; solo revertir si caben.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE gp_projects MODIFY icon TEXT NULL');
        }
    }
};
