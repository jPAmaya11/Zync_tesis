<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial / evidencia propio de cada tarea (GpSubTarea).
 *
 * Inmutable (solo created_at): registra el comentario + adjunto que se exige
 * al finalizar una tarea. Cada entrada pertenece a una sola tarea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_sub_tarea_historial', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sub_tarea_id')
                  ->constrained('gp_sub_tareas')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('comment');
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 100)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('sub_tarea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_sub_tarea_historial');
    }
};
