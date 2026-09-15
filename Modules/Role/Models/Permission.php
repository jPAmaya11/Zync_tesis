<?php

namespace Modules\Role\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends SpatiePermission
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'guard_name',
        'module_id',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
