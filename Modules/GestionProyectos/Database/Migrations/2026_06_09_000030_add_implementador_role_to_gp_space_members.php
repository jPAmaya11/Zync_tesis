<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el rol "implementador" al enum de gp_space_members.
 * Implementador = Ejecutor + Aprobador (crea/edita tareas Y aprueba estados críticos),
 * pero NO gestiona el espacio. Idempotente: el MODIFY deja el enum completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE gp_space_members MODIFY role "
            . "ENUM('propietario','administrador','ejecutor','aprobador','lector','implementador') "
            . "NOT NULL DEFAULT 'ejecutor'"
        );
    }

    public function down(): void
    {
        // Reasigna los implementadores a 'ejecutor' antes de quitar el valor del enum.
        DB::table('gp_space_members')->where('role', 'implementador')->update(['role' => 'ejecutor']);

        DB::statement(
            "ALTER TABLE gp_space_members MODIFY role "
            . "ENUM('propietario','administrador','ejecutor','aprobador','lector') "
            . "NOT NULL DEFAULT 'ejecutor'"
        );
    }
};
