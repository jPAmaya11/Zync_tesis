<?php

namespace Modules\Role\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => $roleId
                ? ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)]
                : 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'name.unique' => 'Ya existe un rol con este nombre.',
            'permissions.array' => 'Los permisos deben ser un array.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del rol',
            'permissions' => 'permisos',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que permissions sea array si está presente
        if ($this->has('permissions') && !is_array($this->permissions)) {
            $this->merge([
                'permissions' => $this->permissions ? [$this->permissions] : []
            ]);
        }
    }
}
