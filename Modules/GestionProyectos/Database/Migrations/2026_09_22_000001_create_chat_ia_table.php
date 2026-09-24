<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de conversaciones del asistente conversacional basado en IA
 * (Google Gemini) — Capítulo 3 de la tesis, tabla "chat_ia".
 *
 * Cada fila es un turno de la conversación: el mensaje del usuario y la
 * respuesta generada por el modelo. 'tipo' distingue el tipo de consulta
 * (por ahora solo 'consulta' de uso general; queda abierto a futuros tipos
 * como 'resumen_tarea', 'sugerencia', etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_ia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_usuario')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->text('mensaje');
            $table->text('respuesta')->nullable();

            $table->timestamp('fecha_creacion')->useCurrent();
            $table->string('tipo', 50)->default('consulta');

            $table->index(['id_usuario', 'fecha_creacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ia');
    }
};
