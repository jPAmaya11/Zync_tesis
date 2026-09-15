<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->foreignId('space_category_id')
                  ->nullable()
                  ->after('categoria')
                  ->constrained('gp_space_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gp_projects', function (Blueprint $table) {
            $table->dropForeign(['space_category_id']);
            $table->dropColumn('space_category_id');
        });
    }
};
