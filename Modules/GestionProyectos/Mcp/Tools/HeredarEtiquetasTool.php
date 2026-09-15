<?php

namespace Modules\GestionProyectos\Mcp\Tools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Concerns\InteractsWithIssues;
use Modules\GestionProyectos\Models\GpLabel;
use Modules\GestionProyectos\Models\GpProject;

/**
 * Copia las etiquetas de otro espacio al espacio destino (sin duplicar).
 */
class HeredarEtiquetasTool extends Tool
{
    use InteractsWithIssues;

    public function name(): string
    {
        return 'heredar-etiquetas';
    }

    public function description(): string
    {
        return 'Copia las etiquetas de otro espacio (from_project_key) al espacio destino (project_key), '
            . 'omitiendo las que ya existen. Requiere ser administrador/propietario del espacio destino.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('project_key')->description('Key del espacio destino, ej. ERP. Obligatorio.')->required();
        $schema->string('from_project_key')->description('Key del espacio de origen del cual copiar las etiquetas. Obligatorio.')->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $data = Validator::make($arguments, [
            'project_key'      => ['required', 'string'],
            'from_project_key' => ['required', 'string'],
        ])->validate();

        if ($data['from_project_key'] === $data['project_key']) {
            return ToolResult::error('No podés heredar etiquetas del mismo espacio.');
        }

        if (! $this->user()->can('manage', $data['project_key'])) {
            return ToolResult::error('No tenés permiso para gestionar etiquetas de ese espacio.');
        }

        if (! GpProject::where('key', $data['project_key'])->exists()) {
            return ToolResult::error('Espacio destino no encontrado: ' . $data['project_key']);
        }
        if (! GpProject::where('key', $data['from_project_key'])->exists()) {
            return ToolResult::error('Espacio de origen no encontrado: ' . $data['from_project_key']);
        }
        if (! $this->canAccessProject($this->user(), $data['from_project_key'])) {
            return ToolResult::error('No tenés acceso al espacio de origen: ' . $data['from_project_key']);
        }

        $existing = GpLabel::forProject($data['project_key'])->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $source   = GpLabel::forProject($data['from_project_key'])->get(['name']);

        $created = 0;
        foreach ($source as $src) {
            if (in_array(mb_strtolower($src->name), $existing, true)) {
                continue;
            }
            GpLabel::create(['project_key' => $data['project_key'], 'name' => $src->name]);
            $existing[] = mb_strtolower($src->name);
            $created++;
        }
        Cache::forget('gp.projects');

        return ToolResult::json([
            'message' => "Se heredaron {$created} etiqueta(s).",
            'created' => $created,
        ]);
    }
}
