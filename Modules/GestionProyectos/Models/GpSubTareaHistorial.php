<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

/**
 * Historial / evidencia de una tarea (GpSubTarea).
 *
 * Inmutable: sin updated_at, sin SoftDeletes. Cada entrada es el comentario
 * (+ adjunto opcional) que se registra al finalizar una tarea.
 */
class GpSubTareaHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'gp_sub_tarea_historial';

    protected $fillable = [
        'sub_tarea_id',
        'user_id',
        'comment',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachments',
        'created_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'attachments' => 'array',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subTarea(): BelongsTo
    {
        return $this->belongsTo(GpSubTarea::class, 'sub_tarea_id');
    }

    // ─── Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /** Lista normalizada de adjuntos: usa el JSON nuevo o cae al legacy de columna única. */
    protected function resolveFiles(): array
    {
        if (is_array($this->attachments) && count($this->attachments)) {
            return $this->attachments;
        }
        if (! empty($this->attachment_path)) {
            return [[
                'path' => $this->attachment_path,
                'name' => $this->attachment_name,
                'mime' => $this->attachment_mime,
            ]];
        }
        return [];
    }

    public function toFrontend(): array
    {
        $hasUser = $this->relationLoaded('user') && $this->user;

        $attachments = [];
        foreach ($this->resolveFiles() as $i => $f) {
            $attachments[] = [
                'name' => $f['name'] ?? 'Adjunto',
                'mime' => $f['mime'] ?? null,
                'url'  => route('gestion-proyectos.subtareas.historial.download', ['id' => $this->id, 'index' => $i]),
            ];
        }

        return [
            'id'              => $this->id,
            'comment'         => $this->comment,
            'attachments'     => $attachments,
            // Legacy (compat): primer adjunto.
            'attachment_name' => $attachments[0]['name'] ?? null,
            'attachment_url'  => $attachments[0]['url'] ?? null,
            'attachment_mime' => $attachments[0]['mime'] ?? null,
            'created_at'      => $this->created_at?->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY, HH:mm'),
            'user_name'       => $hasUser ? $this->user->name : 'Usuario',
            'user_avatar_url' => $hasUser ? ($this->user->avatar_url ?? null) : null,
        ];
    }
}
