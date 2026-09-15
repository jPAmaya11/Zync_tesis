<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proyecto_id');
            $table->unsignedBigInteger('custom_field_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['proyecto_id', 'custom_field_id']);

            $table->foreign('proyecto_id')
                  ->references('id')
                  ->on('gp_proyectos')
                  ->onDelete('cascade');

            $table->foreign('custom_field_id')
                  ->references('id')
                  ->on('gp_custom_fields')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_custom_field_values');
    }
};
