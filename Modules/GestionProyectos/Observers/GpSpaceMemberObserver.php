<?php

namespace Modules\GestionProyectos\Observers;

use Illuminate\Support\Facades\Auth;
use Modules\GestionProyectos\Mail\ScrumSpaceMembershipMail;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Services\Mail\MailDispatcher;
use Modules\GestionProyectos\Support\MailRecipients;

class GpSpaceMemberObserver
{
    public function created(GpSpaceMember $member): void
    {
        $this->notifyMembership($member, 'added');
    }

    public function saved(GpSpaceMember $member): void
    {
        if ($member->role === 'propietario') {
            // Sincronizar owner_id en el proyecto
            $project = GpProject::where('key', $member->project_key)->first();
            if ($project && $project->owner_id !== $member->user_id) {
                // Usar quietUpdate para evitar bucle infinito con GpProjectObserver
                $project->owner_id = $member->user_id;
                $project->saveQuietly();

                // Degradar otros propietarios en el mismo espacio
                GpSpaceMember::where('project_key', $member->project_key)
                    ->where('id', '!=', $member->id)
                    ->where('role', 'propietario')
                    ->update(['role' => 'administrador']);
            }
        }
    }

    public function deleted(GpSpaceMember $member): void
    {
        if ($member->role === 'propietario') {
            $project = GpProject::where('key', $member->project_key)->first();
            if ($project && $project->owner_id === $member->user_id) {
                $project->owner_id = null;
                $project->saveQuietly();
            }
        }

        $this->notifyMembership($member, 'removed');
    }

    private function notifyMembership(GpSpaceMember $member, string $action): void
    {
        $actorId = Auth::id();
        if ($actorId !== null && (int) $actorId === (int) $member->user_id) {
            // El propio usuario se autogestionó (auto-asignación, etc.). No notificarse a sí mismo.
            return;
        }

        $email = MailRecipients::userEmail((int) $member->user_id);
        if ($email === null) {
            return;
        }

        $project   = GpProject::where('key', $member->project_key)->first();
        $actorName = Auth::user()?->name ?? 'Sistema';

        $mail = new ScrumSpaceMembershipMail($member, $action, $actorName, $project);
        app(MailDispatcher::class)->sendMailable(
            $mail,
            [$email],
            meta: [
                'trigger_type' => "scrum.space.member.{$action}",
                'model_type'   => GpSpaceMember::class,
                'model_id'     => $member->id,
            ],
        );
    }
}
