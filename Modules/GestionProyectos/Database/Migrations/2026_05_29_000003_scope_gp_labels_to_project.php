<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas por espacio (no globales).
 *
 * gp_labels pasa de catálogo global (name único) a etiquetas propias de cada
 * espacio: agrega project_key (FK a gp_projects.key) y unique(project_key, name)
 * — el mismo nombre puede existir en distintos espacios, independientes entre sí.
 *
 * El pivot gp_project_labels queda obsoleto (la pertenencia ya la da project_key)
 * y se elimina. Las tablas estaban vacías, por lo que no hay migración de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_labels', function (Blueprint $table) {
            $table->dropUnique('gp_labels_name_unique');
        });

        Schema::table('gp_labels', function (Blueprint $table) {
            $table->string('project_key', 30)->after('id');
            $table->unique(['project_key', 'name']);
            $table->foreign('project_key')
                  ->references('key')->on('gp_projects')
                  ->cascadeOnDelete();
            $table->index('project_key');
        });

        Schema::dropIfExists('gp_project_labels');
    }

    public function down(): void
    {
        // Recrear el pivot (vacío) por consistencia con el estado previo.
        if (!Schema::hasTable('gp_project_labels')) {
            Schema::create('gp_project_labels', function (Blueprint $table) {
                $table->id();
                $table->string('project_key', 30)->index();
                $table->foreignId('label_id')->constrained('gp_labels')->cascadeOnDelete();
                $table->unique(['project_key', 'label_id']);
                $table->foreign('project_key')->references('key')->on('gp_projects')->cascadeOnDelete();
            });
        }

        Schema::table('gp_labels', function (Blueprint $table) {
            $table->dropForeign(['project_key']);
            $table->dropUnique(['project_key', 'name']);
            $table->dropIndex(['project_key']);
            $table->dropColumn('project_key');
            $table->unique('name');
        });
    }
};
