<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subactividades (Proyecto anidado) + Fecha Inicio obligatoria.
 *
 *  - `parent_key` (nullable, indexado): una Subactividad es un Proyecto cuyo parent_key
 *    apunta a la key de su Actividad padre. NULL = actividad top-level.
 *  - Backfill prod-safe: las actividades viejas sin start_date toman su created_at (cero pérdida).
 *  - start_date pasa a NOT NULL: la Fecha Inicio es obligatoria en Actividades y Subactividades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->string('parent_key', 40)->nullable()->after('project')->index();
        });

        // Backfill: actividades sin Fecha Inicio → su fecha de creación.
        DB::table('gp_proyectos')
            ->whereNull('start_date')
            ->update(['start_date' => DB::raw('DATE(created_at)')]);

        // Defensivo: si quedara alguna sin created_at, usar la fecha de hoy.
        DB::table('gp_proyectos')
            ->whereNull('start_date')
            ->update(['start_date' => DB::raw('CURDATE()')]);

        // Fecha Inicio obligatoria.
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->dropIndex(['parent_key']);
            $table->dropColumn('parent_key');
        });
    }
};
