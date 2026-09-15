<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica due_date y fecha_limite en una sola columna: fecha_limite.
 *
 * - Copia max(fecha_limite, due_date) → fecha_limite (preservar el valor más reciente).
 * - Elimina due_date.
 * - down() recrea la columna due_date vacía (no se puede revertir la data).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Preservar data: si fecha_limite es null y due_date tiene valor, lo copiamos.
        DB::statement("
            UPDATE gp_proyectos
            SET fecha_limite = due_date
            WHERE fecha_limite IS NULL AND due_date IS NOT NULL
        ");

        // 2. Drop column.
        Schema::table('gp_proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('gp_proyectos', 'due_date')) {
                $table->dropColumn('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            if (!Schema::hasColumn('gp_proyectos', 'due_date')) {
                $table->date('due_date')->nullable()->after('description');
            }
        });

        // Re-poblar due_date desde fecha_limite (best effort, sin garantía del valor original).
        DB::statement("UPDATE gp_proyectos SET due_date = fecha_limite WHERE fecha_limite IS NOT NULL");
    }
};
