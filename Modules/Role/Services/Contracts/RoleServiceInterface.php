<?php

namespace Modules\Role\Services\Contracts;

use Modules\Role\Models\Role;

interface RoleServiceInterface
{
    public function getRolesWithRelations();
    public function getFormData(): array;
    public function createRole(array $data): Role;
    public function updateRole(Role $role, array $data): Role;
    public function deleteRole(Role $role): bool;
    public function getEstadisticas(): array;
}