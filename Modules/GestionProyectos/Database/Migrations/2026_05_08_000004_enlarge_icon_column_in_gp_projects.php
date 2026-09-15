<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            // Ampliar icon de varchar(20) a text para soportar URLs y dataURLs de imagen
            $table->text('icon')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->string('icon', 20)->nullable()->change();
        });
    }
};
