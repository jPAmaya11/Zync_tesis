<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BUGFIX: la migración 2026_09_15_000001 (rename a roles de tesis) actualiza los
 * VALORES de la columna `role` (ejecutor→desarrollador, aprobador→tester,
 * implementador→disenador) pero nunca amplió el tipo ENUM de la columna, que
 * seguía aceptando solo los nombres viejos. En la práctica esto hace imposible
 * insertar o actualizar un miembro de espacio con los roles nuevos: MySQL/MariaDB
 * trunca el ENUM inválido (error 1265) y la operación falla.
 *
 * Se cambia la columna a VARCHAR(30): los roles válidos ya se validan a nivel de
 * aplicación (GpSpaceMember, Policies, SpaceMembersModal.vue), así que un ENUM
 * rígido en la base de datos no aporta seguridad adicional y sí es frágil ante
 * futuros cambios de nomenclatura.
 */
return new class extends Migration
{
    // Se usa SQL crudo (en vez de Schema::table()->change()) para no depender de
    // doctrine/dbal, que no está instalado en este proyecto.
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE gp_space_members MODIFY role VARCHAR(30) NOT NULL DEFAULT 'desarrollador'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE gp_space_members MODIFY role "
            . "ENUM('propietario','administrador','ejecutor','aprobador','lector','implementador') "
            . "NOT NULL DEFAULT 'ejecutor'"
        );
    }
};
