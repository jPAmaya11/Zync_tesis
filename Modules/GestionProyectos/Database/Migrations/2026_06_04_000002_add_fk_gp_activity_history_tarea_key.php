<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A1 — Agrega FK referencial en gp_activity_history.tarea_key → gp_proyectos.key.
 *
 * Sin esta FK, borrar una actividad dejaba su historial huérfano sin error.
 * Se usa RESTRICT (default MySQL) para que un forceDelete con historial existente
 * falle a nivel DB en lugar de dejar registros colgados.
 *
 * Precondición: gp_proyectos.key es UNIQUE (migración 2026_05_07_000001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_activity_history', function (Blueprint $table) {
            $table->foreign('tarea_key')
                ->references('key')
                ->on('gp_proyectos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gp_activity_history', function (Blueprint $table) {
            $table->dropForeign(['tarea_key']);
        });
    }
};
