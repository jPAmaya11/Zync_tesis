<?php

namespace Modules\GestionProyectos\Policies;

use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\User\Models\User;

class GestionProyectosPolicy
{
    /** Bypass maestro para administradores globales. */
    private function isAdmin(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin', 'super_admin']) || $user->hasPermissionTo('gestion-proyectos.admin');
    }

    public function ver(User $user): bool
    {
        return $user->hasPermissionTo('gestion-proyectos.ver') || $this->isAdmin($user);
    }

    /**
     * Permiso para crear tareas en un proyecto específico.
     * Excepción: el Tester puede pasar este gate para registrar bugs — el controlador
     * exige que el issue_type sea "Error" en ese caso (no puede crear otros tipos).
     */
    public function crear(User $user, string $projectKey): bool
    {
        if ($this->isAdmin($user)) return true;
        if (GpSpaceMember::canWrite($user->id, $projectKey)) return true;
        return GpSpaceMember::canRegisterBug($user->id, $projectKey);
    }

    /**
     * Permiso para registrar historial de actividades en una tarea.
     * Separado de 'crear' para no otorgar al Aprobador capacidad de abrir tareas nuevas.
     * Roles permitidos: cualquier escritor + aprobadores (necesitan adjuntar evidencia).
     */
    public function registrarHistorial(User $user, string $projectKey): bool
    {
        if ($this->isAdmin($user)) return true;
        if (GpSpaceMember::canWrite($user->id, $projectKey)) return true;
        return GpSpaceMember::canApprove($user->id, $projectKey);
    }

    /**
     * Permiso para editar una tarea específica (model-based).
     * Desarrollador/Diseñador/Tester también pasan el gate SOLO si la tarea está
     * asignada a ellos — ProyectoService::update restringe los campos editables a
     * únicamente 'status' en ese caso (no pueden tocar el resto de los datos).
     */
    public function update(User $user, Proyecto $proyecto): bool
    {
        if ($this->isAdmin($user)) return true;
        if (GpSpaceMember::canWrite($user->id, $proyecto->project)) return true;
        // Aprobador puede pasar el gate — ProyectoService restringe a solo status crítico
        if (GpSpaceMember::canApprove($user->id, $proyecto->project)) return true;

        if (
            GpSpaceMember::isRestrictedWorker($user->id, $proyecto->project)
            && (int) $proyecto->assignee_id === (int) $user->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Permiso para comentar (registrar historial) en una tarea específica.
     * Desarrollador/Diseñador/Tester pueden comentar SOLO en sus propias tareas asignadas.
     */
    public function comentarPropia(User $user, Proyecto $proyecto): bool
    {
        if ($this->registrarHistorial($user, $proyecto->project)) return true;

        return GpSpaceMember::isRestrictedWorker($user->id, $proyecto->project)
            && (int) $proyecto->assignee_id === (int) $user->id;
    }

    /**
     * Permiso para edición inline (PATCH) de DATOS de una tarea.
     * Propietario + Implementador (o admin global). El implementador edita los
     * datos de la actividad además de su flujo; el resto de roles no edita datos.
     */
    public function inlineEdit(User $user, Proyecto $proyecto): bool
    {
        if ($this->isAdmin($user)) return true;
        return GpSpaceMember::canInlineEdit($user->id, $proyecto->project);
    }

    /** Permiso para reprogramar una actividad (crea versión sucesora -Rn). Solo aprobadores+. */
    public function reprogramar(User $user, Proyecto $proyecto): bool
    {
        if ($this->isAdmin($user)) return true;
        return GpSpaceMember::canApprove($user->id, $proyecto->project);
    }

    /** Permiso para eliminar una tarea específica (model-based). */
    public function eliminar(User $user, Proyecto $proyecto): bool
    {
        if ($this->isAdmin($user)) return true;
        return GpSpaceMember::canManage($user->id, $proyecto->project);
    }

    /** Permiso para gestionar el espacio (miembros, configuración, equipos). */
    public function manage(User $user, string $projectKey): bool
    {
        if ($this->isAdmin($user)) return true;
        return GpSpaceMember::canManage($user->id, $projectKey);
    }
}
