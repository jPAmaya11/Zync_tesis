<?php

namespace Modules\GestionProyectos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GpMailProvider extends Model
{
    protected $table = 'gp_mail_providers';

    public const STATUS_ONLINE = 'online';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_QUOTA_EXCEEDED = 'quota_exceeded';

    protected $fillable = [
        'provider',
        'enabled',
        'priority',
        'daily_limit',
        'monthly_limit',
        'used_today',
        'used_this_month',
        'last_reset_at',
        'last_month_reset_at',
        'last_sync_at',
        'status',
        'config',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'enabled'             => 'boolean',
        'priority'            => 'integer',
        'daily_limit'         => 'integer',
        'monthly_limit'       => 'integer',
        'used_today'          => 'integer',
        'used_this_month'     => 'integer',
        'config'              => 'array',
        'last_reset_at'       => 'datetime',
        'last_month_reset_at' => 'datetime',
        'last_sync_at'        => 'datetime',
        'last_error_at'       => 'datetime',
    ];

    public function hasDailyQuotaAvailable(): bool
    {
        return $this->used_today < $this->daily_limit;
    }

    public function hasMonthlyQuotaAvailable(): bool
    {
        return $this->used_this_month < $this->monthly_limit;
    }

    /** True solo si ambas cuotas (diaria y mensual) tienen margen. */
    public function hasQuotaAvailable(): bool
    {
        return $this->hasDailyQuotaAvailable() && $this->hasMonthlyQuotaAvailable();
    }

    public function isSelectable(): bool
    {
        return $this->enabled
            && $this->hasQuotaAvailable()
            && !in_array($this->status, [self::STATUS_UNAVAILABLE, self::STATUS_QUOTA_EXCEEDED], true);
    }

    /**
     * Incrementa el consumo contabilizando un crédito por destinatario.
     *
     * SendGrid y Resend descuentan cuota por destinatario final, no por
     * llamada al endpoint. Si se envía un único correo a 5 destinatarios,
     * el proveedor descuenta 5; el contador local debe reflejarlo o el
     * panel mostrará disponibilidad irreal hasta que la sync por API ajuste.
     */
    public function incrementUsed(int $count = 1): void
    {
        $count = max(1, $count);

        DB::table($this->table)
            ->where('id', $this->id)
            ->update([
                'used_today'      => DB::raw("used_today + {$count}"),
                'used_this_month' => DB::raw("used_this_month + {$count}"),
                'updated_at'      => now(),
            ]);

        $this->refresh();

        if (!$this->hasQuotaAvailable() && $this->status !== self::STATUS_QUOTA_EXCEEDED) {
            $this->update(['status' => self::STATUS_QUOTA_EXCEEDED]);
        }
    }

    public function markFailure(string $message, string $newStatus = self::STATUS_DEGRADED): void
    {
        $this->update([
            'status'        => $newStatus,
            'last_error'    => mb_substr($message, 0, 2000),
            'last_error_at' => now(),
        ]);
    }

    public function markRecovered(): void
    {
        if ($this->status === self::STATUS_DEGRADED) {
            $this->update(['status' => self::STATUS_ONLINE]);
        }
    }

    public function resetDailyQuota(): void
    {
        $this->update([
            'used_today'    => 0,
            'last_reset_at' => now(),
        ]);

        $this->refresh();
        $this->tryRestoreOnline();
    }

    public function resetMonthlyQuota(): void
    {
        $this->update([
            'used_this_month'     => 0,
            'last_month_reset_at' => now(),
        ]);

        $this->refresh();
        $this->tryRestoreOnline();
    }

    /**
     * Si está marcado como quota_exceeded pero ambas cuotas ya tienen margen,
     * vuelve a online. Se llama después de resetear diaria o mensual.
     */
    private function tryRestoreOnline(): void
    {
        if ($this->status === self::STATUS_QUOTA_EXCEEDED && $this->hasQuotaAvailable()) {
            $this->update(['status' => self::STATUS_ONLINE]);
        }
    }
}
