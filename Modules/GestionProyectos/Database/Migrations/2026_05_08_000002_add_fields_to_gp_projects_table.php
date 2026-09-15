<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos adicionales a gp_projects:
 *  - categoria   : categoría libre del proyecto (nullable)
 *  - owner_id    : propietario del espacio (FK users)
 *  - assignee_id : usuario asignado al proyecto (FK users)
 *
 * También modifica la longitud de `key` a 5 caracteres máx (estilo JIRA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            // Modificar longitud de key de 30 → 5 (sin re-agregar el índice único)
            $table->string('key', 5)->change();

            // Nuevos campos
            $table->string('categoria', 100)->nullable()->after('description');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete()->after('categoria');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete()->after('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['assignee_id']);
            $table->dropColumn(['categoria', 'owner_id', 'assignee_id']);
            $table->string('key', 30)->change();
        });
    }
};
