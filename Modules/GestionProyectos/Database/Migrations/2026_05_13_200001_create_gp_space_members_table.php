<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de miembros por espacio — Blueprint §2.1 Modelo de Permisos
 *
 * Roles: propietario | administrador | ejecutor | aprobador | lector
 *
 * Reglas clave:
 *  - Solo Administrador y Aprobador pueden mover a Finalizado/Reprogramado.
 *  - El Propietario es único por espacio.
 *  - Un usuario solo puede tener un rol por espacio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_space_members', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 30)
                  ->index()
                  ->comment('FK a gp_projects.key');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->enum('role', ['propietario', 'administrador', 'ejecutor', 'aprobador', 'lector'])
                  ->default('ejecutor');
            $table->timestamps();

            $table->unique(['project_key', 'user_id']);

            $table->foreign('project_key')
                  ->references('key')
                  ->on('gp_projects')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_space_members');
    }
};
