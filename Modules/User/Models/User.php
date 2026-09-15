<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Database\Factories\UserFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $table = 'users';

    /** La factory vive en el módulo, no en database/factories. */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }


    /**
     * Scope para filtrar solo usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'numero_documento',
        'avatar',
        'phone',
        'position',
        'department',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'active'            => 'boolean',
    ];

    /**
     * Usuario que creó a este usuario (created_by). Null para usuarios históricos.
     */
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessor para obtener la URL completa del avatar
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }
        return asset('storage/' . $this->avatar);
    }
}
