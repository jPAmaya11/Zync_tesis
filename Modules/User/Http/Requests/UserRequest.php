<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Role\Models\Role;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validación global: solo un usuario con rol "admin" puede asignar el rol
     * "admin" a otro usuario (al crear o editar). Cualquier otro gestor de usuarios
     * puede asignar el resto de roles, pero nunca admin, aunque tenga permiso
     * usuarios.crear/editar.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleIds = collect($this->input('roles', []))
                ->filter(fn ($r) => $r !== null && $r !== '')
                ->map(fn ($r) => (string) $r);

            if ($roleIds->isEmpty()) {
                return;
            }

            $adminRoleId = (string) (Role::where('name', 'admin')->value('id') ?? '');
            if ($adminRoleId === '') {
                return;
            }

            if ($roleIds->contains($adminRoleId) && ! optional($this->user())->hasRole('admin')) {
                $validator->errors()->add(
                    'roles',
                    'Solo un administrador puede asignar el rol de administrador.'
                );
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId)
            ],
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
            'numero_documento' => 'nullable|string|max:50',
            'active' => 'nullable|boolean',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ];

        // Si no es actualización, la contraseña es requerida con confirmación
        if (!$isUpdate) {
            $rules['password'] = 'required|string';
        } else {
            // En actualización, sin validaciones de contraseña para el admin
            $rules['password'] = 'nullable|string';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'email.required' => 'El email es requerido.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'roles.array' => 'Los roles deben ser un array.',
            'roles.*.exists' => 'Uno o más roles seleccionados no existen.',
            'numero_documento.max' => 'El número de documento no puede exceder 50 caracteres.',
            'active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'department.max' => 'El departamento no puede exceder 255 caracteres.',
            'position.max' => 'El cargo no puede exceder 255 caracteres.',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'roles' => 'roles',
            'numero_documento' => 'número de documento',
            'active' => 'estado activo',
            'department' => 'departamento',
            'position' => 'cargo',
            'phone' => 'teléfono',
        ];
    }
}
