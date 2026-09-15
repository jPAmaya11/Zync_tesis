<?php

namespace Modules\GestionProyectos\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\Proyecto;

/**
 * Espacio de demostración con tareas de ejemplo, para probar el módulo sin
 * tener que crearlo todo a mano.
 *
 * No se ejecuta con `db:seed`; se invoca a propósito:
 *     php artisan db:seed --class="Modules\GestionProyectos\Database\Seeders\DemoSpaceSeeder"
 *
 * Idempotente: re-ejecutarlo no duplica el espacio ni sus tareas.
 */
class DemoSpaceSeeder extends Seeder
{
    private const SPACE_KEY = 'DEMO';

    public function run(): void
    {
        $userId = (int) (DB::table('users')->orderBy('id')->value('id') ?? 0);

        if (! $userId) {
            $this->command?->error('[DemoSpaceSeeder] No hay usuarios en la base de datos; ejecuta primero el seeder principal.');
            return;
        }

        $espacio = GpProject::firstOrCreate(
            ['key' => self::SPACE_KEY],
            [
                'name'        => 'Espacio de demostración',
                'description' => 'Espacio de ejemplo para explorar el módulo.',
                'prefix'      => self::SPACE_KEY,
                'active'      => true,
                'owner_id'    => $userId,
            ]
        );

        GpSpaceMember::firstOrCreate(
            ['project_key' => self::SPACE_KEY, 'user_id' => $userId],
            ['role' => 'propietario', 'suspended' => false]
        );

        if (Proyecto::where('project', self::SPACE_KEY)->exists()) {
            $this->command?->info("[DemoSpaceSeeder] El espacio {$espacio->key} ya tiene tareas; no se crea nada nuevo.");
            return;
        }

        $tareas = [
            ['Configurar el entorno de trabajo', 'Finalizado',  'Media', -14, -7],
            ['Definir el alcance del primer sprint', 'En Curso', 'Alta',  -5,   3],
            ['Revisar los criterios de aceptación', 'Pendiente', 'Media', -2,   6],
            ['Preparar la demo para el equipo',     'Pendiente', 'Baja',   0,  10],
            ['Corregir el reporte de horas',        'En Curso',  'Alta',  -9,  -1],
        ];

        $creadas = [];

        foreach ($tareas as [$resumen, $estado, $prioridad, $diasInicio, $diasLimite]) {
            $creadas[] = Proyecto::create([
                'summary'      => $resumen,
                'status'       => $estado,
                'priority'     => $prioridad,
                'issue_type'   => 'Tarea',
                'project'      => self::SPACE_KEY,
                'assignee_id'  => $userId,
                'reporter_id'  => $userId,
                'creator_id'   => $userId,
                'start_date'   => now()->addDays($diasInicio)->toDateString(),
                'fecha_limite' => now()->addDays($diasLimite)->toDateString(),
            ]);
        }

        GpActivityHistory::create([
            'tarea_key'  => $creadas[1]->key,
            'user_id'    => $userId,
            'comment'    => 'Sprint iniciado con el equipo.',
            'new_status' => 'En Curso',
        ]);

        Cache::forget('gp.projects');
        Cache::forget('gp.labels');

        $this->command?->info(
            '[DemoSpaceSeeder] Espacio ' . self::SPACE_KEY . ' creado con ' . count($creadas) . ' tareas de ejemplo.'
        );
    }
}
