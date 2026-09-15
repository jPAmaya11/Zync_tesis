<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comentario/descripción por MÓDULO de permisos (ej. "Seguimiento", "Gestión Proyectos").
 * Documenta para qué sirve cada GRUPO de permisos, porque en prod se crean permisos y con
 * el tiempo se pierde el contexto de qué hace cada uno.
 *
 * 1:1 con el módulo (module_id ÚNICO): una sola descripción editable por grupo.
 *  - LECTURA: cualquiera que pueda abrir el modal de roles (viaja en el payload de index()).
 *  - ESCRITURA: solo el rol "admin" (validado en ModuleCommentController).
 * Guarda el último editor (updated_by) y la fecha (updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_comments')) {
            return;
        }

        Schema::create('module_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->text('body');
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            // 1:1 — a lo sumo una descripción por módulo (el store hace updateOrCreate).
            $table->unique('module_id');
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_comments');
    }
};
