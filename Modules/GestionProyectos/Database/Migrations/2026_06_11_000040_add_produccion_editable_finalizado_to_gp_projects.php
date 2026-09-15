<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'produccion_editable_finalizado' a gp_projects: switch por-espacio que permite editar
 * inline el campo "Producción" (fecha_aprobacion) aun cuando la actividad está FINALIZADA,
 * esquivando el candado terminal. Aplica solo a fecha_aprobacion y solo en estado Finalizado.
 *
 * Default false: por defecto se mantiene el candado actual (Producción NO editable tras finalizar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gp_projects', 'produccion_editable_finalizado')) {
            return;
        }
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->boolean('produccion_editable_finalizado')->default(false)->after('validar_fechas_inicio');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('gp_projects', 'produccion_editable_finalizado')) {
            return;
        }
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->dropColumn('produccion_editable_finalizado');
        });
    }
};
