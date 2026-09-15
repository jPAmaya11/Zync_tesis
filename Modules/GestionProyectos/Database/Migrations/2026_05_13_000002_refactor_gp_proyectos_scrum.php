<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactoriza gp_proyectos para el espacio SCRUM PROJECT (blueprint v1.0):
 *
 * ELIMINA: tenancy, area_negocios, periodo (reemplazados por campos nuevos)
 *
 * AGREGA:
 *  - software           : Lista configurable por espacio (ej: ERP, Neobrowser)
 *  - entorno            : Lista configurable por espacio (ej: Energy Spain, ALL)
 *  - impacto            : JSON — áreas de empresa afectadas (lista múltiple)
 *  - dias_estimados     : Entero positivo (cálculo: Fecha Inicio + Días → Fecha Límite)
 *  - fecha_limite       : Calculada: start_date + dias_estimados (se guarda para consultas)
 *  - fecha_entrega      : Auto al pasar a "En Revisión" (alias Subida Stage)
 *  - fecha_aprobacion   : Auto al pasar a "Finalizado" (alias Subida Producción)
 *  - aprobado_por_id    : FK users — registrado automáticamente al Finalizar
 *  - fecha_reprogramacion: Auto al pasar a "Reprogramado" — condicional
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            // ── Eliminar campos obsoletos ───────────────────────────────────
            $table->dropColumn(['tenancy', 'area_negocios', 'periodo']);

            // ── Nuevos campos de Software / Entorno (reemplazan Tenancy) ───
            $table->string('software', 100)->nullable()->after('solicitado_por');
            $table->string('entorno', 100)->nullable()->after('software');

            // ── Impacto: áreas de empresa afectadas ─────────────────────────
            $table->json('impacto')->nullable()->after('entorno');

            // ── Planificación ───────────────────────────────────────────────
            $table->unsignedSmallInteger('dias_estimados')->nullable()->after('start_date');
            $table->date('fecha_limite')->nullable()->after('dias_estimados')
                  ->comment('= start_date + dias_estimados, calculado al guardar');

            // ── Fechas automáticas de flujo ─────────────────────────────────
            $table->date('fecha_entrega')->nullable()->after('due_date')
                  ->comment('Se registra al pasar a En Revisión (Subida Stage)');

            $table->date('fecha_aprobacion')->nullable()->after('fecha_entrega')
                  ->comment('Se registra al pasar a Finalizado (Subida Producción)');

            $table->foreignId('aprobado_por_id')
                  ->nullable()
                  ->after('fecha_aprobacion')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Usuario que aprobó (Finalizó) la tarea');

            $table->date('fecha_reprogramacion')->nullable()->after('aprobado_por_id')
                  ->comment('Solo visible/relevante cuando Estado = Reprogramado');
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por_id']);
            $table->dropColumn([
                'software', 'entorno', 'impacto',
                'dias_estimados', 'fecha_limite',
                'fecha_entrega', 'fecha_aprobacion', 'aprobado_por_id', 'fecha_reprogramacion',
            ]);

            // Restaurar columnas eliminadas
            $table->string('tenancy', 100)->nullable();
            $table->string('area_negocios', 100)->nullable();
            $table->string('periodo', 50)->nullable();
        });
    }
};
