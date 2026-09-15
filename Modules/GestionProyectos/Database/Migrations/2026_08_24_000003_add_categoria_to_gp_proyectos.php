<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'categoria' a gp_proyectos: campo GLOBAL del catálogo, presente en todas las
 * actividades, subactividades y reprogramaciones — pasadas, presentes y futuras.
 *
 * Va justo después de 'solicitado_por' y antes de 'team_id' (Equipo), que es la posición
 * pedida en la tabla.
 *
 * Alfanumérico libre (varchar 100). El valor se guarda por NOMBRE, no por id: el catálogo
 * gp_categorias solo alimenta el desplegable, así que si mañana se renombra o borra una
 * categoría, las actividades conservan lo que tenían. Mismo criterio que labels.
 *
 * NULLABLE Y SIN VALOR POR DEFECTO, a propósito: producción tiene muchísimos registros y
 * un UPDATE masivo sobre toda la tabla sería lento y arriesgado. Los históricos quedan
 * vacíos y el campo es opcional siempre, así que esta migración es un ALTER TABLE simple
 * que no reescribe filas ni bloquea nada.
 *
 * IDEMPOTENTE: bajo hasColumn(), re-ejecutarla no falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gp_proyectos', 'categoria')) {
            return;
        }

        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->string('categoria', 100)->nullable()->after('solicitado_por')
                  ->comment('Categoría de la actividad. Catálogo por espacio en gp_categorias');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('gp_proyectos', 'categoria')) {
            return;
        }

        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
