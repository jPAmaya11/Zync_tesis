<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;

class GpMailLog extends Model
{
    protected $table = 'gp_mail_logs';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_FAILED_ALL = 'failed_all';

    protected $fillable = [
        'recipient',
        'provider',
        'subject',
        'payload',
        'response',
        'status',
        'trigger_type',
        'model_type',
        'model_id',
        'attempts',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'payload'  => 'array',
        'attempts' => 'integer',
        'sent_at'  => 'datetime',
    ];

    public function scopeFailedAll($query)
    {
        return $query->where('status', self::STATUS_FAILED_ALL);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * ¿Ya se envió hoy un correo con este trigger/modelo? Se usa para deduplicar
     * alertas recurrentes (ej. SLA cada 30 min) y evitar spam al mismo destinatario.
     *
     * "Hoy" se interpreta en America/Lima para alinearse con el reset diario
     * de cuotas y los crons del módulo.
     */
    public static function alreadySentToday(string $triggerType, string $modelType, int $modelId): bool
    {
        $startOfDay = now('America/Lima')->startOfDay()->utc();

        return static::query()
            ->where('trigger_type', $triggerType)
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->whereIn('status', [self::STATUS_SENT, self::STATUS_PENDING])
            ->where('created_at', '>=', $startOfDay)
            ->exists();
    }
}
