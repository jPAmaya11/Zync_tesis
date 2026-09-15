<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipos por espacio — Blueprint §2.2 columna "Equipo"
 *
 * gp_teams        : definición del equipo (nombre, descripción, proyecto)
 * gp_team_members : pivot users ↔ teams
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Equipos ──────────────────────────────────────────────────────────
        Schema::create('gp_teams', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 30)
                  ->index()
                  ->comment('FK a gp_projects.key — equipo scoped al espacio');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('project_key')
                  ->references('key')
                  ->on('gp_projects')
                  ->cascadeOnDelete();
        });

        // ── Miembros de equipo ────────────────────────────────────────────────
        Schema::create('gp_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                  ->constrained('gp_teams')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_team_members');
        Schema::dropIfExists('gp_teams');
    }
};
