<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

/**
 * Turno de conversación con el asistente de IA (Google Gemini).
 * Tabla: chat_ia — ver migración 2026_09_22_000001_create_chat_ia_table.
 */
class ChatIA extends Model
{
    protected $table = 'chat_ia';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'mensaje',
        'respuesta',
        'fecha_creacion',
        'tipo',
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
