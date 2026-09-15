<?php

namespace Modules\GestionProyectos\Mail;

use Illuminate\Mail\Mailables\Content;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceMember;

class ScrumSpaceMembershipMail extends BaseScrumMail
{
    public string $action;        // 'added' | 'removed'
    public string $spaceName;
    public string $spaceKey;
    public string $role;
    public string $actorName;

    public function __construct(GpSpaceMember $member, string $action, string $actorName, ?GpProject $project = null)
    {
        $this->action    = $action;
        $this->spaceKey  = $member->project_key;
        $project         = $project ?? GpProject::where('key', $member->project_key)->first();
        $this->spaceName = $project?->name ?? $member->project_key;
        $this->role      = $member->role;
        $this->actorName = $actorName;

        $this->subjectLine = $action === 'added'
            ? "Te agregaron al espacio {$this->spaceName}"
            : "Fuiste removido del espacio {$this->spaceName}";
    }

    public function content(): Content
    {
        return new Content(view: 'gestion-proyectos::emails.scrum.space-membership');
    }
}
