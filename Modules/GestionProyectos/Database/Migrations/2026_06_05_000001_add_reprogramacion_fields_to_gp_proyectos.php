<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            // Clave del original raíz (solo en versiones -R1, -R2, etc.)
            $table->string('reprogramacion_root_key', 30)->nullable()->after('fecha_reprogramacion');
            // Número de revisión (1, 2, 3…) — null en actividades normales
            $table->tinyInteger('reprogramacion_n')->unsigned()->nullable()->after('reprogramacion_root_key');

            $table->index('reprogramacion_root_key', 'idx_gp_reprog_root_key');
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropIndex('idx_gp_reprog_root_key');
            $table->dropColumn(['reprogramacion_root_key', 'reprogramacion_n']);
        });
    }
};
