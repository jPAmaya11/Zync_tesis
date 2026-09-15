<?php

namespace Modules\Role\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Role\Models\Module;
use Modules\Role\Models\ModuleComment;

/**
 * CRUD de la descripción por MÓDULO de permisos.
 *  - Lectura: viaja en el payload de RoleController@index (cualquiera con roles.ver).
 *  - Escritura (crear/editar/borrar): SOLO el rol "admin".
 * Es 1:1 por módulo: `store` hace upsert (crear o editar) y `destroy` la elimina.
 */
class ModuleCommentController extends Controller
{
    /** Solo un administrador puede escribir los comentarios de permisos. */
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            optional($request->user())->hasRole('admin'),
            403,
            'Solo un administrador puede editar los comentarios de permisos.'
        );
    }

    /**
     * Upsert de la descripción del módulo (crear o editar). 1:1 por module_id.
     */
    public function store(Request $request, Module $module): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = ModuleComment::updateOrCreate(
            ['module_id' => $module->id],
            ['body' => $data['body'], 'updated_by' => $request->user()->id],
        );

        $comment->load('editor:id,name');

        return response()->json(['comment' => $this->toFrontend($comment)]);
    }

    /**
     * Borrar la descripción del módulo.
     */
    public function destroy(Request $request, Module $module): JsonResponse
    {
        $this->authorizeAdmin($request);

        ModuleComment::where('module_id', $module->id)->delete();

        return response()->json(['comment' => null]);
    }

    private function toFrontend(ModuleComment $c): array
    {
        return [
            'module_id'  => $c->module_id,
            'body'       => $c->body,
            'editor'     => $c->editor?->name,
            'updated_at' => optional($c->updated_at)->toIso8601String(),
        ];
    }
}
