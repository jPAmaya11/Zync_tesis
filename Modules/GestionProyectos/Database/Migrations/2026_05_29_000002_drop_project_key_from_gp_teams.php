<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina gp_teams.project_key — columna muerta.
 *
 * Los equipos son GLOBALES: su pertenencia a espacios se modela en el pivot
 * gp_project_teams (N:N), y su asignación a tareas en gp_proyectos.team_id.
 * La columna project_key (1 equipo ↔ 1 espacio) contradecía ese modelo y nunca
 * se usó (0 filas la tenían seteada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('gp_teams', 'project_key')) {
            return;
        }

        Schema::table('gp_teams', function (Blueprint $table) {
            // Drop FK e índice antes de la columna (nombres por convención Laravel).
            try { $table->dropForeign('gp_teams_project_key_foreign'); } catch (\Throwable) {}
            try { $table->dropIndex('gp_teams_project_key_index'); } catch (\Throwable) {}
        });

        Schema::table('gp_teams', function (Blueprint $table) {
            $table->dropColumn('project_key');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('gp_teams', 'project_key')) {
            return;
        }

        Schema::table('gp_teams', function (Blueprint $table) {
            $table->string('project_key', 30)->nullable()->index()->after('id');
        });

        // No se restaura la FK: los datos originales no son recuperables y el
        // modelo vigente no la requiere.
    }
};
