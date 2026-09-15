<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_audit_log', function (Blueprint $table) {
            $table->id();

            // Quién
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255)->nullable();          // Snapshot del nombre al momento del cambio

            // Qué modelo / registro
            $table->string('model_type', 100)->index();            // Ej: "GpProject", "Proyecto", "GpCustomField"
            $table->unsignedBigInteger('model_id')->nullable()->index();
            $table->string('model_key', 50)->nullable();           // Ej: clave legible (PROY-0001)

            // Qué acción
            $table->enum('action', ['created', 'updated', 'deleted', 'restored'])->index();

            // Detalle del cambio
            $table->json('old_values')->nullable();                // Estado anterior
            $table->json('new_values')->nullable();                // Estado posterior

            // Contexto HTTP
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('method', 10)->nullable();

            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_audit_log');
    }
};
