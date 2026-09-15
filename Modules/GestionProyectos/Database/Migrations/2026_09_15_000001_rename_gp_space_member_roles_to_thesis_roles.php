<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea los roles de gp_space_members con la matriz de roles/permisos de la tesis:
 *
 *   ejecutor      → desarrollador  (trabajador restringido: solo sus tareas asignadas)
 *   aprobador     → tester         (trabajador restringido + registra bugs)
 *   implementador → disenador      (trabajador restringido: solo sus tareas asignadas)
 *
 * 'propietario' y 'administrador' no cambian de valor (administrador se relabela a
 * "Jefe de Proyecto" solo en la interfaz, vía GpSpaceMember::getRoleLabelAttribute()).
 *
 * IMPORTANTE: este cambio también REDUCE los permisos de estos roles (antes creaban y
 * editaban tareas libremente / aprobaban transiciones críticas; ahora solo ven,
 * actualizan el estado y comentan en sus propias tareas asignadas), para que coincida
 * exactamente con la tabla de permisos de la tesis.
 */
return new class extends Migration
{
    private const RENAME = [
        'ejecutor'      => 'desarrollador',
        'aprobador'     => 'tester',
        'implementador' => 'disenador',
    ];

    public function up(): void
    {
        foreach (self::RENAME as $old => $new) {
            DB::table('gp_space_members')->where('role', $old)->update(['role' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAME as $old => $new) {
            DB::table('gp_space_members')->where('role', $new)->update(['role' => $old]);
        }
    }
};
