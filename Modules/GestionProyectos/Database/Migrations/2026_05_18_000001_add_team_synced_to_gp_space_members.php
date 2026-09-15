<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna team_synced a gp_space_members.
 *
 * Semántica:
 *  - true  → el acceso del usuario al espacio se originó por sincronización de equipos.
 *            Será eliminado automáticamente por syncMembersFromTeams() si deja de estar
 *            en cualquier equipo vinculado al espacio, sin importar su rol actual.
 *  - false → el usuario fue agregado manualmente. Su acceso es independiente de equipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_space_members', function (Blueprint $table) {
            $table->boolean('team_synced')
                  ->default(false)
                  ->after('role')
                  ->comment('true = agregado por sync de equipo; false = asignado manualmente');
        });
    }

    public function down(): void
    {
        Schema::table('gp_space_members', function (Blueprint $table) {
            $table->dropColumn('team_synced');
        });
    }
};
