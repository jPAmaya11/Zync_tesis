<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique()->index(); // Clave corta del proyecto (ej: 'ERP', 'IT')
            $table->string('summary', 500); //nombre breve del issue/registro actividad
            $table->string('status', 60)->default('TAREAS POR HACER')->index(); //ESTADO: TAREAS POR HACER, EN PROGRESO, EN ESPERA, TERMINADO
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();// Usuario asignado (FK users)   
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();// Usuario informador (FK users)
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();// Usuario creador (FK users)
            $table->string('priority', 30)->default('Medium')->index();//prioridad: Baja, Media, Alta
            $table->json('labels')->nullable();//etiquetas/tags libres (array de strings)aun en proceso
            $table->string('issue_type', 50)->default('Tarea')->index();//tipo de actividad: Tarea, Bug, Historia, etc hardocodeado por ahora, pero podría ser catálogo aparte en el futuro
            $table->string('project', 30)->default('ERP')->index();//clave del proyecto al que pertenece (ej: 'ERP', 'IT') - podría ser FK a gp_projects pero lo dejamos libre por ahora para mayor flexibilidad
            $table->text('description')->nullable();//campo echo pero no figra aun en la tabla proximamanete se agregara
            $table->date('due_date')->nullable();
            $table->date('start_date')->nullable();
            $table->string('tenancy', 100)->nullable();//tenancy (nombre libre, no FK) //Aun en proceso, podría ser catálogo aparte en el futuro
            $table->string('solicitado_por', 255)->nullable();//solicitado por (nombre libre, no FK)
            $table->string('area_negocios', 100)->nullable();//área de negocios (nombre libre, no FK)//Aun en proceso, podría ser catálogo aparte en el futuro
            $table->string('periodo', 50)->nullable();//periodo (nombre libre, no FK)//Aun en proceso, podría ser catálogo aparte en el futuro
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_proyectos');   
    }
};
