<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_logs', function (Blueprint $table) {
            $table->id();

            // Severidad
            $table->enum('level', ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])
                  ->default('info')
                  ->index();

            // Canal / fuente
            $table->string('channel', 50)->default('gestion-proyectos')->index();

            // Mensaje principal
            $table->text('message');

            // Contexto adicional (array serializado)
            $table->json('context')->nullable();

            // Datos extra de Laravel Monolog
            $table->json('extra')->nullable();

            // Referencia al usuario que originó el evento (puede ser null para jobs/cli)
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Referencia al recurso relacionado (opcional)
            $table->string('loggable_type', 100)->nullable()->index();
            $table->unsignedBigInteger('loggable_id')->nullable()->index();

            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_logs');
    }
};
