<?php

namespace App\Http\Controllers;

use Modules\Role\Models\Module;

use Illuminate\Http\Request;

class ModuleController extends Controller
{
    // =======================
    // MÉTODOS CRUD
    // =======================

    /**
     * Store a newly created module with permissions in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        // Crear módulo básico
        $module = $this->createModule($data);

        // Crear permisos asociados al módulo
        $this->createModulePermissions($module, $data['permissions'] ?? []);

        return response()->json([
            'message' => 'Módulo y permisos creados correctamente.',
            'module' => $module->load('permissions'),
        ]);
    }

    // =======================
    // MÉTODOS AUXILIARES PARA CRUD
    // =======================

    private function createModule($data)
    {
        return Module::create(['name' => $data['name']]);
    }

    private function createModulePermissions($module, $permissions)
    {
        foreach ($permissions as $permissionName) {
            $module->permissions()->create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }
    }

    // =======================
    // REGLAS DE VALIDACIÓN
    // =======================

    private function validationRules(): array
    {
        return [
            'name' => 'required|string|unique:modules,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string|max:255|unique:permissions,name',
        ];
    }
}
