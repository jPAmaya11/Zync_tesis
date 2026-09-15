<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FULLTEXT index sobre summary + description para acelerar búsquedas de texto.
 * La migración es online en InnoDB (no bloquea la tabla durante ALTER TABLE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->fullText(['summary', 'description'], 'gp_proyectos_fulltext_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gp_proyectos', function (Blueprint $table) {
            $table->dropFullText('gp_proyectos_fulltext_idx');
        });
    }
};
