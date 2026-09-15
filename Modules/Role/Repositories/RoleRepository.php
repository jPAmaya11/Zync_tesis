<?php

namespace Modules\Role\Repositories;

use Modules\Role\Models\Role;
use Modules\Role\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    protected $role;

    public function __construct(Role $role)
    {
        $this->role = $role;
    }
    
}