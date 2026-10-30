<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el rol "implementador" al enum de gp_space_members.
 * Implementador = Ejecutor + Aprobador (crea/edita tareas Y aprueba estados críticos),
 * pero NO gestiona el espacio. Idempotente: el MODIFY deja el enum completo.
 *
 * En PostgreSQL no hay un ENUM nativo editable aquí (Schema::enum() se traduce a
 * VARCHAR + CHECK constraint), así que directamente se quita ese CHECK: la
 * migración 2026_09_25_000001 termina de convertir la columna a VARCHAR(30) sin
 * restricción, que es el estado final deseado de todas formas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE gp_space_members DROP CONSTRAINT IF EXISTS gp_space_members_role_check');

            return;
        }

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

        if (DB::getDriverName() === 'pgsql') {
            // El CHECK constraint original ya fue eliminado arriba; no se reconstruye.
            return;
        }

        DB::statement(
            "ALTER TABLE gp_space_members MODIFY role "
            . "ENUM('propietario','administrador','ejecutor','aprobador','lector') "
            . "NOT NULL DEFAULT 'ejecutor'"
        );
    }
};
