<?php

namespace Modules\GestionProyectos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GestionProyectosIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestion-proyectos.ver') ?? false;
    }

    public function rules(): array
    {
        return [
            'project'      => 'nullable|string|max:30',
            'statuses'     => 'nullable|array',
            'statuses.*'   => 'string|max:60',
            'assignees'    => 'nullable|array',
            'assignees.*'  => 'string|max:30',
            'priorities'   => 'nullable|array',
            'priorities.*' => 'string|max:30',
            'issue_types'  => 'nullable|array',
            'issue_types.*'=> 'string|max:50',
            'labels'       => 'nullable|array',
            'labels.*'     => 'string|max:100',
            'group_by'     => 'nullable|in:status,assignee,priority,labels,project,tenancy,issue_type',
            'search'       => 'nullable|string|max:200',
            'order_by'     => 'nullable|string|max:60',
            'page'             => 'nullable|integer|min:1',
            'next_page_token'  => 'nullable|string|max:20', // alias de page para compat. con frontend
            'maxResults'       => 'nullable|integer|min:1|max:100',
        ];
    }
}
