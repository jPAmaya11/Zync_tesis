<?php

namespace Modules\GestionProyectos\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\GestionProyectos\Models\GpMailLog;
use Modules\GestionProyectos\Models\GpMailProvider;
use Modules\GestionProyectos\Services\Mail\MailProviderResolver;
use Throwable;

class MailProvidersController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, MailProviderResolver $resolver): InertiaResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $providers = GpMailProvider::query()
            ->orderBy('priority')
            ->get()
            ->map(function (GpMailProvider $p) use ($resolver) {
                // Enriquecer cada row con `supports_usage_api` para que el frontend
                // decida si mostrar el botón de "Sincronizar contadores".
                $supports = false;
                try {
                    $supports = $resolver->instantiate($p)->supportsUsageApi();
                } catch (Throwable) {
                    // provider desconocido o sin clase asociada → no soporta usage api
                }
                $p->setAttribute('supports_usage_api', $supports);
                return $p;
            });

        $logsQuery = GpMailLog::query()
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->when($request->filled('status'),   fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('trigger'),  fn ($q) => $q->where('trigger_type', $request->string('trigger')))
            ->when($request->filled('recipient'), fn ($q) => $q->where('recipient', 'like', '%' . $request->string('recipient') . '%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at');

        $logs = $logsQuery->paginate(25)->withQueryString();

        $hasFailedAllRecent = GpMailLog::failedAll()->recent(24)->exists();

        return Inertia::render('GestionProyectos/Mail/Providers', [
            'providers'          => $providers,
            'logs'               => $logs,
            'filters'            => $request->only(['provider', 'status', 'trigger', 'recipient', 'from', 'to']),
            'hasFailedAllRecent' => $hasFailedAllRecent,
            'providerStatuses'   => [
                GpMailProvider::STATUS_ONLINE,
                GpMailProvider::STATUS_DEGRADED,
                GpMailProvider::STATUS_UNAVAILABLE,
                GpMailProvider::STATUS_QUOTA_EXCEEDED,
            ],
            'logStatuses' => [
                GpMailLog::STATUS_PENDING,
                GpMailLog::STATUS_SENT,
                GpMailLog::STATUS_FAILED,
                GpMailLog::STATUS_FAILED_ALL,
            ],
        ]);
    }

    public function update(Request $request, GpMailProvider $provider): RedirectResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $data = $request->validate([
            'enabled'       => ['sometimes', 'boolean'],
            'priority'      => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'daily_limit'   => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'monthly_limit' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'status'        => ['sometimes', 'in:online,degraded,unavailable,quota_exceeded'],
        ]);

        $provider->update($data);

        return back()->with('success', "Provider {$provider->provider} actualizado.");
    }

    public function resetQuota(GpMailProvider $provider): RedirectResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $provider->resetDailyQuota();

        return back()->with('success', "Cuota diaria de {$provider->provider} reseteada.");
    }

    public function resetMonthlyQuota(GpMailProvider $provider): RedirectResponse
    {
        $this->authorize('gestion-proyectos.admin');

        $provider->resetMonthlyQuota();

        return back()->with('success', "Cuota mensual de {$provider->provider} reseteada.");
    }

    /**
     * Sincroniza los contadores del provider con la API real del proveedor
     * (ej. SendGrid /v3/stats). Sólo aplica a providers que soporten usage API.
     */
    public function syncUsage(GpMailProvider $provider, MailProviderResolver $resolver): RedirectResponse
    {
        $this->authorize('gestion-proyectos.admin');

        try {
            $instance = $resolver->instantiate($provider);
        } catch (Throwable $e) {
            return back()->with('error', "Provider {$provider->provider} no es reconocido por el sistema.");
        }

        if (!$instance->supportsUsageApi()) {
            return back()->with('error', "El provider {$provider->provider} no expone una API de consumo (no se puede sincronizar).");
        }

        try {
            $stats = $instance->fetchUsageStats();
        } catch (Throwable $e) {
            $provider->update([
                'last_error'    => mb_substr($e->getMessage(), 0, 2000),
                'last_error_at' => now(),
            ]);
            return back()->with('error', "Error al sincronizar con {$provider->provider}: {$e->getMessage()}");
        }

        $provider->update([
            'used_today'      => (int) ($stats['used_today'] ?? 0),
            'used_this_month' => (int) ($stats['used_this_month'] ?? 0),
            'last_sync_at'    => now(),
        ]);

        $provider->refresh();

        if ($provider->status === GpMailProvider::STATUS_QUOTA_EXCEEDED && $provider->hasQuotaAvailable()) {
            $provider->update(['status' => GpMailProvider::STATUS_ONLINE]);
        }

        return back()->with('success', "Contadores de {$provider->provider} sincronizados con la API.");
    }
}
