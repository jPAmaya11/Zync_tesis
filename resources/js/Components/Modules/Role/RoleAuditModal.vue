<template>
    <ModalView
        :show="show"
        :title="titulo"
        :subtitle="subtitulo"
        size="3xl"
        :hide-footer="true"
        @close="$emit('close')"
    >
        <!-- ── Barra de filtros (todo INLINE dentro de este modal: sin overlays encima) ── -->
        <div class="mb-3">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        @click="showFiltros = !showFiltros"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors',
                            showFiltros
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20'
                                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400',
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                        Filtros
                        <span v-if="filtrosActivos" class="ml-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-indigo-600 text-white text-[10px] font-bold">{{ filtrosActivos }}</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="showFiltros ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>

                    <!-- Resumen de filtros activos cuando el panel está COLAPSADO -->
                    <template v-if="!showFiltros">
                        <span
                            v-for="a in accionesSelObjs"
                            :key="a.value"
                            class="inline-flex items-center px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-medium"
                        >{{ a.label }}</span>
                        <span
                            v-if="fechaDesde || fechaHasta"
                            class="inline-flex items-center px-2.5 py-1 rounded-full bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-xs font-medium"
                        >{{ fechaDesde || '…' }} → {{ fechaHasta || '…' }}</span>
                    </template>

                    <button v-if="filtrosActivos" type="button" @click="limpiarFiltros" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 underline">Limpiar todo</button>
                </div>
                <span v-if="meta.total" class="text-xs text-gray-400 shrink-0">{{ meta.total }} registro{{ meta.total === 1 ? '' : 's' }}</span>
            </div>

            <!-- Panel de filtros COLAPSABLE (inline). Acción (chips multi-selección) + fechas. -->
            <div v-show="showFiltros" class="mt-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-4 space-y-4">
                <!-- Rango de fechas -->
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Rango de fechas</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Desde</label>
                            <input type="date" v-model="fechaDesde" :max="fechaHasta || undefined" @change="fetchLogs(1)"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                            <input type="date" v-model="fechaHasta" :min="fechaDesde || undefined" @change="fetchLogs(1)"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>

                <!-- Acción (chips) -->
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Acción</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="a in actions"
                            :key="a.value"
                            type="button"
                            @click="toggleAccion(a.value)"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-sm font-medium border transition-all',
                                accionesSel.includes(a.value)
                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                                    : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-600 hover:border-indigo-400',
                            ]"
                        >
                            {{ a.label }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cargando inicial -->
        <div v-if="loading && !logs.length" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
            <span class="ml-2 text-sm text-gray-500">Cargando auditoría…</span>
        </div>

        <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg px-3 py-2">{{ error }}</p>

        <div v-else-if="!logs.length" class="flex flex-col items-center justify-center py-12 gap-2 text-center">
            <svg class="w-10 h-10 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-sm text-gray-400">{{ filtrosActivos ? 'Sin registros para el filtro aplicado' : 'Sin registros de auditoría' }}</p>
        </div>

        <!-- Lista (altura fija cuando hay varias páginas) -->
        <ul
            v-else
            :class="[
                'divide-y divide-gray-100 dark:divide-gray-700/60',
                meta.last_page > 1 ? 'h-[55vh] overflow-y-auto custom-scrollbar' : '',
                loading ? 'opacity-50 transition-opacity' : '',
            ]"
        >
            <li v-for="log in logs" :key="log.id" class="py-3">
                <div class="flex items-center gap-3">
                    <div class="w-40 shrink-0 flex justify-center">
                        <span :class="['px-2.5 py-1 text-xs font-bold rounded-full border whitespace-nowrap text-center', badgeClass(log.action)]">
                            {{ log.action_label }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-800 dark:text-gray-100">
                            <span class="font-semibold">{{ log.role_name || '—' }}</span>
                            <span v-if="log.target_user_name" class="text-gray-500 dark:text-gray-400"> · usuario <span class="font-medium text-gray-700 dark:text-gray-200">{{ log.target_user_name }}</span></span>
                        </p>

                        <!-- Diff -->
                        <p v-if="log.action === 'name_changed'" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            «{{ log.old_values?.name || '—' }}» → «{{ log.new_values?.name || '—' }}»
                        </p>
                        <p v-else-if="log.new_values?.agregados?.length || log.old_values?.removidos?.length" class="text-xs mt-0.5 space-x-2">
                            <span v-if="log.new_values?.agregados?.length" class="text-emerald-600 dark:text-emerald-400">+ {{ log.new_values.agregados.join(', ') }}</span>
                            <span v-if="log.old_values?.removidos?.length" class="text-red-600 dark:text-red-400">− {{ log.old_values.removidos.join(', ') }}</span>
                        </p>
                        <p v-else-if="log.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ log.description }}</p>

                        <p class="text-[13px] text-gray-400 mt-1">
                            por <span class="font-medium text-gray-500 dark:text-gray-300">{{ log.user_name }}</span>
                            · {{ log.created_at }}
                            <span v-if="log.ip_address" class="text-gray-300 dark:text-gray-600"> · {{ log.ip_address }}</span>
                        </p>
                    </div>
                </div>
            </li>
        </ul>

        <!-- Paginación -->
        <div v-if="!loading && !error && meta.total" class="mt-3 flex items-center justify-between gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
            <span class="text-xs text-gray-400">{{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
            <div v-if="meta.last_page > 1" class="flex items-center gap-1">
                <button type="button" @click="prev" :disabled="meta.current_page <= 1"
                    class="inline-flex items-center justify-center h-7 px-2 rounded-md text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <span class="text-xs font-medium text-gray-500 tabular-nums px-1.5">{{ meta.current_page }} / {{ meta.last_page }}</span>
                <button type="button" @click="next" :disabled="meta.current_page >= meta.last_page"
                    class="inline-flex items-center justify-center h-7 px-2 rounded-md text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        </div>
    </ModalView>
</template>

<script setup>
import ModalView from '@/Components/Modals/ModalView.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    // Scope opcional. Con roleId → la auditoría se acota a ESE rol (botón ojo de la card).
    // Sin roleId (null) → vista UNIFICADA de todo el módulo (botón "Auditoría" superior).
    roleId: { type: Number, default: null },
    roleName: { type: String, default: '' },
});
defineEmits(['close']);

const titulo = computed(() => (props.roleId ? `Auditoría · ${props.roleName || 'rol'}` : 'Auditoría de Roles'));
const subtitulo = computed(() => (props.roleId
    ? 'Movimientos registrados solo de este rol'
    : 'Historial de movimientos del módulo (solo administradores)'));

const logs = ref([]);
const actions = ref([]);            // [{value,label}] que devuelve el backend (labels user-friendly)
const loading = ref(false);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });

// ── Estado de filtros (todo inline; sin overlays) ──────────────────────────
const showFiltros = ref(false);
const accionesSel = ref([]);        // valores de acción seleccionados: ['deleted', ...]
const fechaDesde = ref('');
const fechaHasta = ref('');

// Para el resumen de chips cuando el panel está colapsado.
const accionesSelObjs = computed(() => actions.value.filter((a) => accionesSel.value.includes(a.value)));
const filtrosActivos = computed(() => accionesSel.value.length + ((fechaDesde.value || fechaHasta.value) ? 1 : 0));

// Estilo SUAVE del proyecto (igual que StatusBadge): fondo tenue + texto de color +
// borde sutil, con variantes dark (el modal es oscuro). Nada de rellenos saturados.
function badgeClass(action) {
    const map = {
        created:             'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30',
        deleted:             'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/30',
        restored:            'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-500/10 dark:text-teal-300 dark:border-teal-500/30',
        name_changed:        'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/30',
        permissions_changed: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30',
        user_assigned:       'bg-green-50 text-green-700 border-green-200 dark:bg-green-500/10 dark:text-green-300 dark:border-green-500/30',
        user_unassigned:     'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:border-orange-500/30',
    };
    return map[action] || 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-700/40 dark:text-gray-300 dark:border-gray-600';
}

async function fetchLogs(page = 1) {
    loading.value = true;
    error.value = '';
    try {
        const params = {
            page,
            // Scope opcional a un rol (botón ojo); sin él = auditoría unificada.
            role_id: props.roleId || undefined,
            // Varias acciones → coma; el backend hace whereIn (explode).
            action: accionesSel.value.length ? accionesSel.value.join(',') : undefined,
            date_from: fechaDesde.value || undefined,
            date_to: fechaHasta.value || undefined,
        };
        const { data } = await window.axios.get(route('roles.auditoria', params));
        logs.value = data.logs || [];
        actions.value = data.actions || [];
        meta.value = data.meta || { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 };
    } catch (e) {
        error.value = e.response?.status === 403
            ? 'Solo un administrador puede ver la auditoría.'
            : (e.response ? 'No se pudo cargar la auditoría.' : 'Error de conexión.');
        logs.value = [];
    } finally {
        loading.value = false;
    }
}

// Toggle de una acción (multi-selección) → aplica en vivo.
function toggleAccion(value) {
    accionesSel.value = accionesSel.value.includes(value)
        ? accionesSel.value.filter((v) => v !== value)
        : [...accionesSel.value, value];
    fetchLogs(1);
}

function limpiarFiltros() {
    accionesSel.value = [];
    fechaDesde.value = '';
    fechaHasta.value = '';
    fetchLogs(1);
}

function prev() { if (meta.value.current_page > 1) fetchLogs(meta.value.current_page - 1); }
function next() { if (meta.value.current_page < meta.value.last_page) fetchLogs(meta.value.current_page + 1); }

watch(
    () => props.show,
    (val) => {
        if (!val) return;
        // Cada apertura arranca limpia: el scope lo fija roleId; los filtros no se arrastran
        // entre un rol y otro (ni entre la vista unificada y una scopeada).
        accionesSel.value = [];
        fechaDesde.value = '';
        fechaHasta.value = '';
        showFiltros.value = false;
        fetchLogs(1);
    }
);
</script>
