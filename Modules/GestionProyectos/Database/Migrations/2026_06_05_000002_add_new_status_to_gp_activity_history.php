<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_activity_history', function (Blueprint $table) {
            // Estado destino al que esta entrada sirve de evidencia (Finalizado / Reprogramado / Cancelado).
            // null = comentario general, no es evidencia de transición.
            $table->string('new_status', 60)->nullable()->after('comment');
            $table->index(['tarea_key', 'new_status'], 'idx_gp_hist_tarea_newstatus');
        });
    }

    public function down(): void
    {
        Schema::table('gp_activity_history', function (Blueprint $table) {
            $table->dropIndex('idx_gp_hist_tarea_newstatus');
            $table->dropColumn('new_status');
        });
    }
};
