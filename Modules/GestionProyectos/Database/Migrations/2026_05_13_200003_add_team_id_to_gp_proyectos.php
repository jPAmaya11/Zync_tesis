<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega team_id a gp_proyectos — columna "Equipo" del Blueprint §2.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->foreignId('team_id')
                  ->nullable()
                  ->after('solicitado_por')
                  ->constrained('gp_teams')
                  ->nullOnDelete()
                  ->comment('Equipo responsable de la tarea (FK gp_teams)');
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
