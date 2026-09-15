<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía gp_projects.icon de TEXT (~64 KB) a MEDIUMTEXT (~16 MB).
 * El ícono se guarda como dataURL base64; un logo de 200 KB ≈ 273 KB de base64,
 * que no cabía en TEXT (causaba "Data too long for column 'icon'").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE gp_projects MODIFY icon MEDIUMTEXT NULL');
    }

    public function down(): void
    {
        // Volver a TEXT podría truncar íconos grandes; solo revertir si caben.
        DB::statement('ALTER TABLE gp_projects MODIFY icon TEXT NULL');
    }
};
