<script setup>
import { ref, reactive, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BackButton from '@/Components/Navigation/BackButton.vue'
import Actions from '@/Components/Utilities/Actions.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
    providers: { type: Array, required: true },
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    hasFailedAllRecent: { type: Boolean, default: false },
    providerStatuses: { type: Array, default: () => [] },
    logStatuses: { type: Array, default: () => [] },
})

const editing = ref(null)
const editForm = useForm({
    enabled: true,
    priority: 1,
    daily_limit: 100,
    monthly_limit: 3000,
    status: 'online',
})

function openEdit(provider) {
    editing.value = provider
    editForm.enabled = !!provider.enabled
    editForm.priority = provider.priority
    editForm.daily_limit = provider.daily_limit
    editForm.monthly_limit = provider.monthly_limit ?? 3000
    editForm.status = provider.status
}

function closeEdit() {
    editing.value = null
    editForm.reset()
}

function saveEdit() {
    if (!editing.value) return
    editForm.patch(route('gestion-proyectos.mail.providers.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: closeEdit,
    })
}

function quickToggle(provider) {
    router.patch(
        route('gestion-proyectos.mail.providers.update', provider.id),
        { enabled: !provider.enabled },
        { preserveScroll: true },
    )
}

function confirmQuotaReset({ provider, scope, routeName }) {
    const isDark = document.documentElement.classList.contains('dark')
    const scopeLabel = scope === 'monthly' ? 'mensual' : 'diario'
    const triggerHint = scope === 'monthly'
        ? 'El reset automático (día 1 del mes a las 00:00 Lima) no corrió'
        : 'El reset automático (00:00 Lima) no corrió'

    Swal.fire({
        title: `¿Reiniciar contador ${scopeLabel}?`,
        html: `
            <div style="text-align:left; font-size:14px; line-height:1.55;">
                <p style="margin:0 0 12px; padding:10px 12px; border-radius:6px; background:${isDark ? '#78350f' : '#fffbeb'}; color:${isDark ? '#fef3c7' : '#92400e'}; border:1px solid ${isDark ? '#b45309' : '#fde68a'};">
                    ⚠️ NO resetea los envíos en <strong>${provider.provider}</strong> ni afecta los correos ya enviados.
                    Sólo reinicia el contador ${scopeLabel} interno del sistema.
                </p>
                <p style="margin:12px 0 6px; font-weight:600;">Útil si:</p>
                <ul style="margin:0; padding-left:20px;">
                    <li>${triggerHint}</li>
                    <li>Subiste el plan del provider</li>
                    <li>El contador se desincronizó</li>
                </ul>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, reiniciar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#056599',
        cancelButtonColor: '#6b7280',
        background: isDark ? '#1f2937' : '#ffffff',
        color: isDark ? '#f3f4f6' : '#111827',
        focusCancel: true,
    }).then((result) => {
        if (!result.isConfirmed) return
        router.post(route(routeName, provider.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    title: `Contador ${scopeLabel} reiniciado`,
                    text: `${provider.provider} vuelve a estar disponible.`,
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                    background: isDark ? '#1f2937' : '#ffffff',
                    color: isDark ? '#f3f4f6' : '#111827',
                })
            },
        })
    })
}

function resetQuota(provider) {
    confirmQuotaReset({
        provider,
        scope: 'daily',
        routeName: 'gestion-proyectos.mail.providers.reset-quota',
    })
}

function resetMonthlyQuota(provider) {
    confirmQuotaReset({
        provider,
        scope: 'monthly',
        routeName: 'gestion-proyectos.mail.providers.reset-monthly-quota',
    })
}

function syncUsage(provider) {
    const isDark = document.documentElement.classList.contains('dark')

    Swal.fire({
        title: `¿Sincronizar contadores de ${provider.provider}?`,
        html: `
            <div style="text-align:left; font-size:14px; line-height:1.55;">
                <p style="margin:0 0 12px;">
                    Llama a la API de <strong>${provider.provider}</strong> y actualiza
                    <code>used_today</code> y <code>used_this_month</code> con el dato real
                    que reporta el provider para el mes en curso.
                </p>
                <p style="margin:0 0 6px; font-weight:600;">Útil cuando:</p>
                <ul style="margin:0; padding-left:20px;">
                    <li>El contador local quedó desfasado tras un reseteo de BD</li>
                    <li>Querés confirmar el dato oficial antes de tomar decisiones</li>
                    <li>El cron horario aún no corrió y necesitás datos frescos ya</li>
                </ul>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, sincronizar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        background: isDark ? '#1f2937' : '#ffffff',
        color: isDark ? '#f3f4f6' : '#111827',
    }).then((result) => {
        if (!result.isConfirmed) return
        router.post(route('gestion-proyectos.mail.providers.sync-usage', provider.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    title: 'Contadores sincronizados',
                    text: `${provider.provider} ahora refleja el consumo real del provider.`,
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                    background: isDark ? '#1f2937' : '#ffffff',
                    color: isDark ? '#f3f4f6' : '#111827',
                })
            },
            onError: () => {
                Swal.fire({
                    title: 'Error al sincronizar',
                    text: 'Revisa el panel y los logs. Puede ser un problema temporal del provider.',
                    icon: 'error',
                    background: isDark ? '#1f2937' : '#ffffff',
                    color: isDark ? '#f3f4f6' : '#111827',
                })
            },
        })
    })
}

// Formato amigable "hace 5 min" / "hace 2h" para last_sync_at
const lastSyncLabel = (iso) => {
    if (!iso) return 'Nunca'
    const date = new Date(iso)
    const diffSec = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000))
    if (diffSec < 60)       return `Hace ${diffSec}s`
    if (diffSec < 3600)     return `Hace ${Math.floor(diffSec / 60)} min`
    if (diffSec < 86400)    return `Hace ${Math.floor(diffSec / 3600)} h`
    return `Hace ${Math.floor(diffSec / 86400)} d`
}

const filterForm = reactive({
    provider: props.filters.provider ?? '',
    status: props.filters.status ?? '',
    trigger: props.filters.trigger ?? '',
    recipient: props.filters.recipient ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
})

function applyFilters() {
    router.get(route('gestion-proyectos.mail.providers.index'), { ...filterForm }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function clearFilters() {
    Object.keys(filterForm).forEach(k => { filterForm[k] = '' })
    applyFilters()
}

const statusBadge = (status) => {
    const map = {
        online: 'bg-green-100 text-green-800',
        degraded: 'bg-yellow-100 text-yellow-800',
        unavailable: 'bg-red-100 text-red-800',
        quota_exceeded: 'bg-orange-100 text-orange-800',
        sent: 'bg-green-100 text-green-800',
        pending: 'bg-gray-100 text-gray-800',
        failed: 'bg-red-100 text-red-800',
        failed_all: 'bg-red-200 text-red-900 font-semibold',
    }
    return map[status] ?? 'bg-gray-100 text-gray-800'
}

const usagePercent = (p) => {
    if (!p.daily_limit) return 0
    return Math.min(100, Math.round((p.used_today / p.daily_limit) * 100))
}

const monthlyUsagePercent = (p) => {
    if (!p.monthly_limit) return 0
    return Math.min(100, Math.round(((p.used_this_month ?? 0) / p.monthly_limit) * 100))
}

const usageBarColor = (pct) => {
    if (pct >= 90) return 'bg-red-500'
    if (pct >= 70) return 'bg-amber-500'
    return 'bg-indigo-500'
}
</script>

<template>
    <Head title="Configuración de Mail" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-white dark:bg-gray-900 py-6 px-6 sm:px-6 rounded-md">
            <div class="max-w-8xl mx-auto space-y-6 space-x-6">

                <!-- Header con título estilo módulo (dos líneas de colores en el pie) -->
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        
                        <BackButton :href="route('gestion-proyectos.index')" />
                
                        <h2 class="text-2xl font-bold tituloPag ml-4">
                            Configuración de Mail
                        </h2>
                       
                    </div>
                    
                </div>

            <!-- Alerta cuando hubo failed_all en las últimas 24h -->
            <div v-if="hasFailedAllRecent" class="rounded-lg border border-red-300 dark:border-red-700/40 bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-800 dark:text-red-200">
                <div class="font-semibold mb-1">⚠️ Atención: hay correos que no pudieron entregarse</div>
                <p>En las últimas 24 horas hubo intentos donde <strong>todos los providers fallaron</strong>. Revisa los logs abajo (status = failed_all) y verifica el estado de los providers.</p>
            </div>

            <!-- Providers -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Proveedores configurados</h3>
                    <!-- <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Orden de envío por prioridad. El sistema usa failover automático.</p> -->
                </div>

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Provider</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Prioridad</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Cuota diaria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Cuota mensual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Habilitado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Último error</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="p in providers" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100 capitalize">{{ p.provider }}</td>
                            <td class="px-6 py-4">
                                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', statusBadge(p.status)]">
                                    {{ p.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ p.priority }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center space-x-2">
                                    <span class="tabular-nums">{{ p.used_today }} / {{ p.daily_limit }}</span>
                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                        <div :class="['h-1.5 rounded-full transition-all', usageBarColor(usagePercent(p))]"
                                             :style="{ width: usagePercent(p) + '%' }"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="tabular-nums">{{ p.used_this_month ?? 0 }} / {{ p.monthly_limit ?? 0 }}</span>
                                        <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                            <div :class="['h-1.5 rounded-full transition-all', usageBarColor(monthlyUsagePercent(p))]"
                                                 :style="{ width: monthlyUsagePercent(p) + '%' }"></div>
                                        </div>
                                    </div>
                                    <span v-if="p.supports_usage_api"
                                          class="text-[10px] text-gray-500 dark:text-gray-400 leading-none"
                                          :title="p.last_sync_at ? new Date(p.last_sync_at).toLocaleString() : 'Nunca sincronizado con la API del provider'">
                                        ⟳ Sync: {{ lastSyncLabel(p.last_sync_at) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <button @click="quickToggle(p)"
                                        :class="['inline-flex h-6 w-11 items-center rounded-full transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800',
                                                 p.enabled ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600']">
                                    <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition', p.enabled ? 'translate-x-6' : 'translate-x-1']" />
                                </button>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="p.last_error">{{ p.last_error || '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Actions
                                    :edit="true"
                                    edit-title="Editar provider"
                                    :can-reset-quota="true"
                                    reset-quota-title="Reiniciar contador diario"
                                    :can-reset-monthly-quota="true"
                                    reset-monthly-quota-title="Reiniciar contador mensual"
                                    :can-sync-usage="!!p.supports_usage_api"
                                    sync-usage-title="Sincronizar contadores con la API del provider"
                                    @edit="openEdit(p)"
                                    @reset-quota="resetQuota(p)"
                                    @reset-monthly-quota="resetMonthlyQuota(p)"
                                    @sync-usage="syncUsage(p)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Filtros logs -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Logs de envío</h3>
                    <span v-if="logs.total" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ logs.total }} {{ logs.total === 1 ? 'registro' : 'registros' }}
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
                    <select v-model="filterForm.provider" class="rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los providers</option>
                        <option v-for="p in providers" :key="p.provider" :value="p.provider">{{ p.provider }}</option>
                    </select>
                    <select v-model="filterForm.status" class="rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los estados</option>
                        <option v-for="s in logStatuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <input v-model="filterForm.trigger" placeholder="Trigger (ej. scrum.task.created)"
                           class="rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <input v-model="filterForm.recipient" placeholder="Destinatario"
                           class="rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <input v-model="filterForm.from" type="date"
                           class="rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <input v-model="filterForm.to" type="date"
                           class="rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div class="flex space-x-2">
                    <button @click="applyFilters" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Aplicar
                    </button>
                    <button @click="clearFilters" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Limpiar
                    </button>
                </div>
            </div>

            <!-- Tabla logs -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-indigo-50 dark:bg-indigo-900/30">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Destinatario</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Asunto</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Trigger</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Provider</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-indigo-600 dark:text-white uppercase tracking-wider">Intentos</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ log.created_at }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 truncate max-w-xs" :title="log.recipient">{{ log.recipient }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 truncate max-w-md" :title="log.subject">{{ log.subject }}</td>
                                <td class="px-4 py-3 text-xs font-mono text-indigo-600 dark:text-indigo-400">{{ log.trigger_type ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 capitalize">{{ log.provider ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', statusBadge(log.status)]">
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 tabular-nums">{{ log.attempts }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                        Sin registros para los filtros actuales.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-900/40">
                    <div class="text-gray-500 dark:text-gray-400">
                        Página <span class="font-medium text-gray-700 dark:text-gray-200">{{ logs.current_page }}</span> de <span class="font-medium text-gray-700 dark:text-gray-200">{{ logs.last_page }}</span>
                    </div>
                    <div class="flex space-x-1">
                        <a v-for="link in logs.links" :key="link.label"
                           v-html="link.label"
                           :href="link.url"
                           :class="['px-3 py-1 rounded border text-xs transition',
                                    link.active
                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                                    !link.url ? 'opacity-40 pointer-events-none' : '']" />
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Modal edición -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="closeEdit">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6 space-y-5 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 capitalize">
                            Editar {{ editing.provider }}
                        </h3>
                        <button @click="closeEdit" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Habilitado</label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="editForm.enabled" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-indigo-600 focus:ring-indigo-500" />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Provider activo en el failover</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Prioridad <span class="text-gray-400 dark:text-gray-500 font-normal">(menor = primero)</span></label>
                        <input type="number" v-model.number="editForm.priority" min="1" max="1000"
                               class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Límite diario</label>
                            <input type="number" v-model.number="editForm.daily_limit" min="0"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Límite mensual</label>
                            <input type="number" v-model.number="editForm.monthly_limit" min="0"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Estado</label>
                        <select v-model="editForm.status"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="s in providerStatuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="closeEdit"
                                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            Cancelar
                        </button>
                        <button @click="saveEdit" :disabled="editForm.processing"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            {{ editForm.processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Override scoped: en modo oscuro las cabeceras quedan blancas.
   Necesario porque app.css define `.dark .text-indigo-600 { color: lavanda !important }`,
   que pisa el utility `dark:text-white` de Tailwind con misma especificidad. */
.dark thead th {
    color: #ffffff !important;
}
</style>
