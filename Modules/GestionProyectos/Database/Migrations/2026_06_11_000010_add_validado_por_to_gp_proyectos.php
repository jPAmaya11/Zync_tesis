<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'validado_por_id' a gp_proyectos: usuario que VALIDA el "aprobado por" de una
 * actividad/subactividad/reprogramación ya finalizada. Campo libre (sin lógica de negocio
 * amarrada); solo lo setean los aprobadores/admin, y solo cuando el registro está Finalizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gp_proyectos', 'validado_por_id')) {
            return;
        }
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->foreignId('validado_por_id')->nullable()->after('aprobado_por_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('gp_proyectos', 'validado_por_id')) {
            return;
        }
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validado_por_id');
        });
    }
};
