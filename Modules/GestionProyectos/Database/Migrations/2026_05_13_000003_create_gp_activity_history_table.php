<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de historial de actividades por tarea.
 *
 * Obligatorio al pasar a "Finalizado" o "Reprogramado" (blueprint §2.4):
 * el sistema exige un comentario y un adjunto. Sin ambos, el cambio no se guarda.
 *
 * Solo tiene created_at (inmutable — no se edita el historial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_activity_history', function (Blueprint $table) {
            $table->id();

            $table->string('tarea_key', 30)->index()
                  ->comment('Clave de la tarea (ej: ERP-0001)');

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Usuario que registró la entrada');

            $table->text('comment')
                  ->comment('Comentario obligatorio al Finalizar o Reprogramar');

            $table->string('attachment_path', 500)->nullable()
                  ->comment('Ruta del archivo adjunto en storage');

            $table->string('attachment_name', 255)->nullable()
                  ->comment('Nombre original del archivo subido');

            $table->string('attachment_mime', 100)->nullable();

            // Solo created_at — el historial es inmutable
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_activity_history');
    }
};
