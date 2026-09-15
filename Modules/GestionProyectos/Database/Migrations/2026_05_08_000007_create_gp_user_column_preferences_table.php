<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_user_column_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('project_key', 30);
            // JSON: [{ key, visible, order, field_id?, name? }, ...]
            $table->json('columns');
            $table->timestamps();

            $table->unique(['user_id', 'project_key']);

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_user_column_preferences');
    }
};
