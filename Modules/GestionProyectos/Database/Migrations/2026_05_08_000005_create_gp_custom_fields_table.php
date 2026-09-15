<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 30)->index();
            $table->string('name', 100);
            // Tipos: text, number, date, select, checkbox
            $table->string('type', 30)->default('text');
            // Para tipo 'select': array de opciones ["Opción A", "Opción B"]
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('project_key')
                  ->references('key')
                  ->on('gp_projects')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_custom_fields');
    }
};
