<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Información de documento de identidad
            $table->string('numero_documento', 20)->nullable();

            $table->string('avatar')->nullable();

            // Información de contacto
            $table->string('phone', 20)->nullable();

            // Información laboral
            $table->string('position')->nullable();
            $table->string('department')->nullable();

            // Información de actividad
            $table->timestamp('last_login_at')->nullable();
            $table->json('preferences')->nullable();
            $table->boolean('is_active')->default(true);

            // Información adicional de perfil
            $table->string('location')->nullable();
            $table->string('timezone', 50)->default('America/Lima');
            $table->string('language', 10)->default('es');

            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->boolean('active')->default(true);
            $table->softDeletes();

            // Índices para optimizar consultas
            $table->index('numero_documento');
            $table->index('department');
            $table->index('position');
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
