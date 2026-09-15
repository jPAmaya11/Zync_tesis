<?php

namespace Modules\GestionProyectos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionProyectosStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crear', $this->input('project_key')) ?? false;
    }

    public function rules(): array
    {
        return [
            'project_key'         => ['required', 'string', 'max:30'],
            'summary'             => ['required', 'string', 'max:500'],
            'issue_type'          => ['required', 'string', 'max:50'],
            'status'              => ['nullable', 'string', 'max:60'],
            'priority'            => ['nullable', 'string', 'max:30'],
            'description'         => ['nullable', 'string', 'max:10000'],
            'assignee_account_id' => ['nullable', 'string', 'max:30'],
            'reporter_account_id' => ['nullable', 'string', 'max:30'],
            'fecha_limite'        => ['nullable', 'date_format:Y-m-d'],
            'start_date'          => ['required', 'date_format:Y-m-d'],
            'solicitado_por'      => ['nullable', 'string', 'max:255'],
            'categoria'           => ['nullable', 'string', 'max:100'],
            'software'            => ['nullable', 'string', 'max:100'],
            'entorno'             => ['nullable', 'string', 'max:100'],
            'impacto'             => ['nullable', 'array'],
            'impacto.*'           => ['string', Rule::in(config('gestion-proyectos.impacto_values', []))],
            'dias_estimados'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'team_id'             => ['nullable', 'integer', 'exists:gp_teams,id'],
            // La Fecha Límite es solo un margen estimado: las fechas de subida a Stage/Producción
            // pueden ser posteriores a ella sin restricción.
            'fecha_entrega'    => ['nullable', 'date_format:Y-m-d'],
            'fecha_aprobacion' => ['nullable', 'date_format:Y-m-d'],
            'labels'              => ['nullable', 'array'],
            'labels.*'            => ['string', 'max:100'],
            'custom_fields'       => ['nullable', 'array'],
            'custom_fields.*'     => ['nullable', 'string', 'max:2000'],
        ];
    }
}
