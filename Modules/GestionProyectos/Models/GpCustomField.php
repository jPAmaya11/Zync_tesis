<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GpCustomField extends Model
{
    protected $table = 'gp_custom_fields';

    protected $fillable = [
        'project_key',
        'name',
        'type',
        'options',
        'order',
        'active',
        'is_default',
    ];

    protected $casts = [
        'options'    => 'array',
        'active'     => 'boolean',
        'is_default' => 'boolean',
        'order'      => 'integer',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(GpProject::class, 'project_key', 'key');
    }

    public function values(): HasMany
    {
        return $this->hasMany(GpCustomFieldValue::class, 'custom_field_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Key única para usar en columnas: "custom_123" */
    public function getColumnKeyAttribute(): string
    {
        return 'custom_' . $this->id;
    }

    /** Serialización para el frontend */
    public function toFrontend(): array
    {
        return [
            'id'          => $this->id,
            'column_key'  => $this->column_key,
            'project_key' => $this->project_key,
            'name'        => $this->name,
            'type'        => $this->type,
            'options'     => $this->options ?? [],
            'order'       => $this->order,
            'active'      => $this->active,
            'is_default'  => (bool) $this->is_default,
        ];
    }
}
