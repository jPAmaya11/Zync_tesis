<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega "suspended" a gp_space_members para la suspensión REVERSIBLE de acceso
 * cuando se desactiva un equipo (la "puerta cerrada").
 *
 * Semántica:
 *  - suspended = false → membresía operativa (acceso normal).
 *  - suspended = true  → el acceso está SUSPENDIDO: la fila se conserva (rol intacto)
 *                        pero NO concede acceso. Solo aplica a miembros venidos de un
 *                        equipo (team_synced=true) cuyos equipos en ese espacio quedaron
 *                        todos inactivos. Al reactivar un equipo se des-suspende y el rol
 *                        vuelve "como antes". Los miembros manuales (team_synced=false) y
 *                        el propietario NUNCA se suspenden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_space_members', function (Blueprint $table) {
            $table->boolean('suspended')
                  ->default(false)
                  ->after('team_synced')
                  ->comment('true = acceso suspendido por equipo inactivo (rol preservado, reversible)');
        });
    }

    public function down(): void
    {
        Schema::table('gp_space_members', function (Blueprint $table) {
            $table->dropColumn('suspended');
        });
    }
};
