<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna JSON `attachments` a gp_sub_tarea_historial para soportar
 * múltiples adjuntos por evidencia (máx. 3). Las columnas legacy de un solo
 * adjunto (attachment_path/name/mime) se conservan para evidencias antiguas.
 * Guard `hasColumn` → idempotente y segura en cualquier entorno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gp_sub_tarea_historial', 'attachments')) {
            Schema::table('gp_sub_tarea_historial', function (Blueprint $table) {
                $table->json('attachments')->nullable()->after('attachment_mime');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gp_sub_tarea_historial', 'attachments')) {
            Schema::table('gp_sub_tarea_historial', function (Blueprint $table) {
                $table->dropColumn('attachments');
            });
        }
    }
};
