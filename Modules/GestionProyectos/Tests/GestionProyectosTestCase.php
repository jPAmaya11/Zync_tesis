<?php

namespace Modules\GestionProyectos\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionProyectos\Models\GpActivityHistory;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\GpSubTarea;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Base de los tests del módulo Gestión de Proyectos (SCRUM).
 *
 * El modelo de acceso es POR ESPACIO: un usuario no tiene rol global, tiene un rol en
 * gp_space_members para cada espacio (propietario/administrador/ejecutor/aprobador/
 * lector/implementador). El permiso global 'gestion-proyectos.ver' solo abre la ruta;
 * la autorización real la resuelve GpSpaceMember dentro del controller/servicio.
 * Por eso casi todo fixture necesita: espacio + membresía + actividad.
 */
abstract class GestionProyectosTestCase extends TestCase
{
    use RefreshDatabase;

    /** Contador de keys de espacio: gp_projects.key es varchar(5), uniqid() no cabe. */
    private static int $spaceSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::$spaceSeq = 0;
    }

    /** Usuario con el permiso global que abre las rutas del módulo (sin rol de espacio). */
    protected function usuario(array $attrs = []): User
    {
        Permission::findOrCreate('gestion-proyectos.ver', 'web');

        $u = User::factory()->create(array_merge([
            'name'     => 'User ' . uniqid(),
            'email'    => 'u' . uniqid() . '@test.local',
            'password' => bcrypt('secret'),
        ], $attrs));
        $u->givePermissionTo('gestion-proyectos.ver');

        return $u;
    }

    /** Admin global: bypass en todos los métodos de GpSpaceMember y en la Policy. */
    protected function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $u = $this->usuario();
        $u->assignRole('admin');

        return $u;
    }

    /**
     * Espacio SCRUM. El key va sin uniqid porque la columna es varchar(5); además el key
     * del espacio es el PREFIJO de las actividades que cuelgan de él (S0001-0001).
     */
    protected function espacio(array $o = []): GpProject
    {
        $key = 'S' . str_pad((string) (++self::$spaceSeq), 4, '0', STR_PAD_LEFT);

        return GpProject::create(array_merge([
            'key'        => $key,
            'name'       => 'Espacio ' . $key,
            'space_type' => 'SCRUM_PROJECT',
            'active'     => true,
        ], $o));
    }

    /** Da de alta a un usuario en un espacio con un rol concreto. */
    protected function miembro(User $user, GpProject $espacio, string $role, bool $suspended = false): GpSpaceMember
    {
        return GpSpaceMember::create([
            'project_key' => $espacio->key,
            'user_id'     => $user->id,
            'role'        => $role,
            'suspended'   => $suspended,
        ]);
    }

    /** Actividad top-level del espacio. start_date es NOT NULL en gp_proyectos. */
    protected function actividad(GpProject $espacio, array $o = []): Proyecto
    {
        return Proyecto::create(array_merge([
            'summary'    => 'Actividad de prueba',
            'status'     => 'Pendiente',
            'project'    => $espacio->key,
            'issue_type' => 'Tarea',
            'priority'   => 'Media',
            'start_date' => now()->toDateString(),
        ], $o));
    }

    /** Subactividad (mismo modelo, cuelga por parent_key → key -Sn). */
    protected function subActividad(Proyecto $padre, array $o = []): Proyecto
    {
        return $this->actividad(
            GpProject::where('key', $padre->project)->firstOrFail(),
            array_merge(['parent_key' => $padre->key], $o)
        );
    }

    /** Tarea de una actividad (1 nivel, sin aprobación). */
    protected function subTarea(Proyecto $padre, array $o = []): GpSubTarea
    {
        return GpSubTarea::create(array_merge([
            'key'        => $padre->key . '-T' . uniqid(),
            'parent_key' => $padre->key,
            'summary'    => 'Tarea de prueba',
            'status'     => 'Pendiente',
        ], $o));
    }

    /**
     * Evidencia de una transición: entrada de gp_activity_history con new_status.
     * El servicio exige que la ÚLTIMA evidencia apunte al estado destino, así que
     * los tests que encadenan evidencias dependen del orden (created_at, id).
     */
    protected function evidencia(Proyecto $actividad, string $newStatus, ?User $autor = null): GpActivityHistory
    {
        return GpActivityHistory::create([
            'tarea_key'  => $actividad->key,
            'user_id'    => $autor?->id,
            'comment'    => 'Evidencia para ' . $newStatus,
            'new_status' => $newStatus,
        ]);
    }
}
