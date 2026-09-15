<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el estado Activo/Inactivo a los equipos (gp_teams).
 *
 * Semántica:
 *  - is_active = true  → equipo operativo: se ofrece en selectores y puede asignarse
 *                        a espacios y actividades.
 *  - is_active = false → equipo "archivado": deja de ofrecerse para NUEVAS asignaciones,
 *                        pero NO toca a sus miembros, ni sus accesos al espacio, ni las
 *                        actividades ya asignadas (team_id histórico). Es reversible.
 *
 * Desactivar un equipo es siempre seguro: no elimina personas ni trabajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_teams', function (Blueprint $table) {
            $table->boolean('is_active')
                  ->default(true)
                  ->after('description')
                  ->comment('true = equipo operativo; false = archivado (no asignable, sin tocar miembros)');
        });
    }

    public function down(): void
    {
        Schema::table('gp_teams', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
