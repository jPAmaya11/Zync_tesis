<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega tipo de espacio y prefijo de ID a gp_projects.
 * space_type: SCRUM_PROJECT | TICKET_SUPPORT
 * prefix: clave corta para autogenerar IDs (ej: "ERP", "TI")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->string('space_type', 30)
                  ->default('SCRUM_PROJECT')
                  ->after('key')
                  ->comment('Tipo de espacio: SCRUM_PROJECT | TICKET_SUPPORT');

            $table->string('prefix', 10)
                  ->nullable()
                  ->after('space_type')
                  ->comment('Prefijo para autogenerar IDs de tarea, ej: ERP → ERP-0001');
        });
    }

    public function down(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->dropColumn(['space_type', 'prefix']);
        });
    }
};
