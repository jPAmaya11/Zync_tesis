<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            // Renombrar avatar_url → icon y acortar a 20 chars (emoji/texto corto)
            $table->renameColumn('avatar_url', 'icon');
        });

        // Cambiar longitud a 20 chars (suficiente para emoji + texto corto)
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->string('icon', 20)->nullable()->change();
        });

        // Eliminar columnas que ya no se usan
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->dropColumn(['description', 'color', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->renameColumn('icon', 'avatar_url');
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->smallInteger('order')->default(0);
        });
    }
};
