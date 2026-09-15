<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría inmutable del módulo de Roles: registra cada movimiento sensible
 * (crear/editar/eliminar rol, cambio de nombre, cambio de permisos/carteras/reportes,
 * asignación/remoción de usuarios). Solo el rol "admin" puede consultarla.
 *
 * Las entradas son inmutables (ver RoleAuditLog: bloquea updating/deleting).
 * Se guardan snapshots de nombres para que el log sobreviva al borrado del rol/usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_audit_logs')) {
            return;
        }

        Schema::create('role_audit_logs', function (Blueprint $table) {
            $table->id();

            // Quién hizo la acción (snapshot del nombre)
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255)->nullable();

            // Sobre qué rol (snapshot del nombre)
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->string('role_name', 255)->nullable();

            // Tipo de evento
            $table->string('action', 40)->index();

            // Usuario afectado (en asignación/remoción)
            $table->unsignedBigInteger('target_user_id')->nullable()->index();
            $table->string('target_user_name', 255)->nullable();

            // Diff y resumen
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description', 500)->nullable();

            // Contexto forense de la petición
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('method', 10)->nullable();

            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('target_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_audit_logs');
    }
};
