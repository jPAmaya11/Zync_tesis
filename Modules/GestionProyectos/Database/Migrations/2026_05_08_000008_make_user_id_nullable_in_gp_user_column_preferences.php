<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_user_column_preferences', function (Blueprint $table) {
            // 1. Eliminar FK y unique anteriores
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'project_key']);

            // 2. Hacer user_id nullable (null = default del proyecto)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // 3. Recrear unique y FK
            $table->unique(['user_id', 'project_key']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('gp_user_column_preferences', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'project_key']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'project_key']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
