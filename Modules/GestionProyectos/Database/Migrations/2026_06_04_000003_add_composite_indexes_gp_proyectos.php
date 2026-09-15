<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A2 — Índices compuestos en gp_proyectos para queries frecuentes en producción.
 *
 * Sin estos índices, los filtros combinados (espacio+estado, asignado+estado, etc.)
 * hacen full-table-scan — crítico con cientos de actividades por espacio.
 *
 * Índices añadidos:
 *   [project, status]      — filtro de tabla por estado (el más común)
 *   [project, deleted_at]  — base de casi toda consulta con SoftDeletes
 *   [assignee_id, status]  — "mis tareas pendientes"
 *   [status, created_at]   — reportes ordenados cronológicamente por estado
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->index(['project', 'status'],      'gp_proyectos_project_status_idx');
            $table->index(['project', 'deleted_at'],  'gp_proyectos_project_deleted_at_idx');
            $table->index(['assignee_id', 'status'],  'gp_proyectos_assignee_status_idx');
            $table->index(['status', 'created_at'],   'gp_proyectos_status_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropIndex('gp_proyectos_project_status_idx');
            $table->dropIndex('gp_proyectos_project_deleted_at_idx');
            $table->dropIndex('gp_proyectos_assignee_status_idx');
            $table->dropIndex('gp_proyectos_status_created_at_idx');
        });
    }
};
