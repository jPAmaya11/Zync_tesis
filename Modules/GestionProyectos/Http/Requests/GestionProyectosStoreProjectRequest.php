<?php

namespace Modules\GestionProyectos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionProyectosStoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestion-proyectos.admin');
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:150'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'key'                => ['required', 'string', 'max:5', 'alpha_num', 'uppercase', Rule::unique('gp_projects', 'key')->whereNull('deleted_at')],
            'space_type'         => ['nullable', 'string', 'in:SCRUM_PROJECT'],
            'prefix'             => ['nullable', 'string', 'max:10'],
            'categoria'          => ['nullable', 'string', 'max:100'],
            'space_category_id'  => ['nullable', 'integer', 'exists:gp_space_categories,id'],
            // base64 del ícono: 200 KB de imagen ≈ 273 KB de base64. Tope ~300 KB.
            'icon'               => ['nullable', 'string', 'max:300000'],
            'owner_id'           => ['nullable', 'integer', 'exists:users,id'],
            'team_ids'           => ['nullable', 'array'],
            'team_ids.*'         => ['integer', 'exists:gp_teams,id'],
            // Switch "Bloquear fechas anteriores" (piso de Fecha de Inicio al crear/reprogramar).
            'validar_fechas_inicio' => ['nullable', 'boolean'],
            // Switch "Producción editable tras finalizar" (editar fecha_aprobacion aun Finalizado).
            'produccion_editable_finalizado' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required'  => 'La clave del proyecto es obligatoria.',
            'key.max'       => 'La clave no puede tener más de 5 caracteres.',
            'key.alpha_num' => 'La clave solo puede contener letras y números.',
            'key.uppercase' => 'La clave debe estar en mayúsculas.',
            'key.unique'    => 'Ya existe un proyecto con esta clave.',
            'name.required' => 'El nombre del proyecto es obligatorio.',
            'icon.max'      => 'El ícono es muy grande. La imagen no puede superar los 200 KB.',
            'team_ids.array' => 'La selección de equipos debe ser una lista válida.',

        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalizar la clave a mayúsculas antes de validar
        if ($this->has('key')) {
            $this->merge(['key' => strtoupper(trim((string) $this->key))]);
        }

        // Asegurar que team_ids sea un array si viene como algo distinto a nulo
        if ($this->has('team_ids') && !is_array($this->team_ids)) {
            $this->merge(['team_ids' => $this->team_ids ? [$this->team_ids] : []]);
        }
    }
}
