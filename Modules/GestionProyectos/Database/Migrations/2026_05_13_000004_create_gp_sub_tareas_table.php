<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub Tareas — Blueprint §2.5
 *
 * Sub tareas simples (estilo Todoist) que dependen de una tarea padre.
 * - Solo 1 nivel de profundidad (parent → sub tarea, nunca sub-sub)
 * - Sin capa de aprobación
 * - Usuario define cada campo libremente (no hereda del padre)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_sub_tareas', function (Blueprint $table) {
            $table->id();

            // Clave única legible (ej: ERP-0001-1, ERP-0001-2)
            $table->string('key', 50)->unique()->index();

            // Referencia al padre (gp_proyectos.key)
            $table->string('parent_key', 30)->index();
            $table->foreign('parent_key')
                  ->references('key')
                  ->on('gp_proyectos')
                  ->onDelete('cascade');

            // Campos obligatorios
            $table->string('summary', 500);

            // Campos opcionales libres (el usuario define cada uno)
            $table->text('description')->nullable();
            $table->text('observacion')->nullable();

            // Asignación
            $table->foreignId('assignee_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('creator_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Estado simple (Pendiente / Finalizado — blueprint §2.5)
            $table->string('status', 30)->default('Pendiente')->index();

            // Prioridad
            $table->string('priority', 30)->nullable();

            // Fechas
            $table->date('start_date')->nullable();
            $table->date('fecha_entrega')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_sub_tareas');
    }
};
