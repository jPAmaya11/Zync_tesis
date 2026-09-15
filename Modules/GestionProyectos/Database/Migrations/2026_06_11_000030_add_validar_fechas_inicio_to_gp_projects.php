<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'validar_fechas_inicio' a gp_projects: switch por-espacio que controla el piso de la
 * Fecha de Inicio al crear/reprogramar. Con true (default) se bloquean fechas anteriores:
 *  - actividad raíz: inicio no anterior a hoy (America/Lima),
 *  - subactividad / reprogramación / reprog. de subactividad: inicio no anterior al del padre.
 * Con false, la fecha de inicio queda libre (cualquier fecha).
 *
 * Default true para que TODOS los espacios existentes conserven el comportamiento actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gp_projects', 'validar_fechas_inicio')) {
            return;
        }
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->boolean('validar_fechas_inicio')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('gp_projects', 'validar_fechas_inicio')) {
            return;
        }
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->dropColumn('validar_fechas_inicio');
        });
    }
};
