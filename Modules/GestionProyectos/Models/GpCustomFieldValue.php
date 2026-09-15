<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpCustomFieldValue extends Model
{
    protected $table = 'gp_custom_field_values';

    protected $fillable = [
        'proyecto_id',
        'custom_field_id',
        'value',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(GpCustomField::class, 'custom_field_id');
    }
}
