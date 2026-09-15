<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `description` (opcional) a gp_projects.
 *
 * La migración original de creación declara esta columna, pero la tabla real en
 * dev/prod no la tiene (la migración de creación se editó después de ejecutarse).
 * Por eso se agrega aquí con guard `hasColumn` → idempotente y segura en cualquier
 * entorno (no falla si ya existiera). Los espacios actuales quedan con NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gp_projects', 'description')) {
            Schema::table('gp_projects', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gp_projects', 'description')) {
            Schema::table('gp_projects', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
