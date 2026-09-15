<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo global de etiquetas
        Schema::create('gp_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // Tabla pivote: un proyecto puede tener muchas etiquetas
        // y una etiqueta puede estar en muchos proyectos
        Schema::create('gp_project_labels', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 30)->index();
            $table->foreignId('label_id')->constrained('gp_labels')->cascadeOnDelete();
            $table->unique(['project_key', 'label_id']);

            $table->foreign('project_key')
                  ->references('key')
                  ->on('gp_projects')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_project_labels');
        Schema::dropIfExists('gp_labels');
    }
};
