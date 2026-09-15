<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de acceso a la API REST de GestionProyectos.
 *
 * Cada usuario genera el suyo desde la web (Operaciones → Generar Token) y lo
 * envía como `Authorization: Bearer <token>`. El token se guarda HASHEADO (sha256);
 * el texto plano solo se muestra una vez al crearlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name')->default('API Token');     // etiqueta libre (ej. "Chat externo")
            $table->string('token', 64)->unique();            // sha256 hex = 64 chars
            $table->json('abilities')->nullable();            // p.ej. ['*'] o ['issues:read']
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_api_tokens');
    }
};
