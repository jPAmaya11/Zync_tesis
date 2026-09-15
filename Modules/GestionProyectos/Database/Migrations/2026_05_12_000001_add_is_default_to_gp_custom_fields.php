<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_custom_fields', function (Blueprint $table) {
            // Marca campos sembrados automáticamente al crear el proyecto.
            // Los campos con is_default = true no pueden borrarse desde la UI.
            $table->boolean('is_default')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('gp_custom_fields', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
