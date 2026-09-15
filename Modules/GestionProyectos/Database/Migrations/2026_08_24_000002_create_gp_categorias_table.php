<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de CATEGORÍAS por espacio, con la misma filosofía que las etiquetas
 * (gp_labels): no es una lista cerrada que haya que mantener a mano, sino un catálogo
 * que CRECE SOLO. La primera vez alguien escribe la categoría a mano; a partir de ahí
 * queda disponible en el desplegable de ese espacio.
 *
 * Scope por espacio, igual que las etiquetas: unique(project_key, name) permite que el
 * mismo nombre exista en espacios distintos sin mezclarse, y el FK con cascadeOnDelete
 * limpia el catálogo si se borra el espacio.
 *
 * OJO — no confundir con gp_space_categories, que categoriza los ESPACIOS entre sí.
 * Esta tabla categoriza las ACTIVIDADES dentro de un espacio.
 *
 * El valor se guarda por NOMBRE en gp_proyectos.categoria (igual que labels guarda
 * nombres, no ids): así una actividad conserva su texto aunque el catálogo cambie.
 *
 * IDEMPOTENTE: bajo hasTable(), re-ejecutarla no falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gp_categorias')) {
            return;
        }

        Schema::create('gp_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 30);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['project_key', 'name']);
            $table->index('project_key');

            $table->foreign('project_key')
                  ->references('key')
                  ->on('gp_projects')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_categorias');
    }
};
