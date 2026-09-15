<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de PROYECTOS (cabecera/catálogo).
 * Cada fila define un proyecto; los registros/issues van en gp_proyectos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_projects', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();          // Clave corta: 'ERP', 'IT', 'PROY'
            $table->string('name', 150);                   // Nombre legible
            $table->text('description')->nullable();       // Descripción opcional
            $table->string('avatar_url', 500)->nullable(); // Logo/imagen
            $table->string('color', 20)->nullable();       // Color de acento (#hex)
            $table->json('settings')->nullable();          // Configuración extra libre
            $table->boolean('active')->default(true);      // Ocultar sin eliminar
            $table->unsignedSmallInteger('order')->default(0); // Orden en sidebar/dropdown
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_projects');
    }
};
