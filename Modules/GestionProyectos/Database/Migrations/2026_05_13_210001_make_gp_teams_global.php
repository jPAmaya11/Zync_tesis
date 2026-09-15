<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Hacer que gp_teams.project_key sea nullable (para equipos globales)
        Schema::table('gp_teams', function (Blueprint $table) {
            $table->string('project_key', 30)->nullable()->change();
        });

        // 2. Crear tabla pivote gp_project_teams
        Schema::create('gp_project_teams', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 30);
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->foreign('project_key')->references('key')->on('gp_projects')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('gp_teams')->onDelete('cascade');
            
            $table->unique(['project_key', 'team_id']);
        });

        // 3. Migrar relaciones existentes (si existen equipos con project_key, ponerlos en la pivote)
        $existingTeams = \Illuminate\Support\Facades\DB::table('gp_teams')
            ->whereNotNull('project_key')
            ->get();

        foreach ($existingTeams as $team) {
            \Illuminate\Support\Facades\DB::table('gp_project_teams')->insert([
                'project_key' => $team->project_key,
                'team_id'    => $team->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gp_project_teams');
        
        Schema::table('gp_teams', function (Blueprint $table) {
            $table->string('project_key', 30)->nullable(false)->change();
        });
    }
};
