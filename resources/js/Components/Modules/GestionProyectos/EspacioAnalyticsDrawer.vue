<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-[60] bg-black/40 backdrop-blur-[2px]" @click="$emit('close')" />
        </Transition>

        <!-- Drawer (slide desde la derecha, transición lenta) -->
        <Transition name="slide-drawer">
            <aside
                v-if="show"
                class="fixed top-0 right-0 z-[61] h-full w-full max-w-[480px] bg-white dark:bg-gray-900 shadow-2xl border-l border-gray-200 dark:border-gray-700 flex flex-col"
            >
                <!-- Header (indigo completo) -->
                <div class="shrink-0 flex items-center gap-3 px-5 py-4 bg-indigo-600 dark:bg-indigo-600 shadow-md">
                    <div class="w-9 h-9 rounded-lg bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white leading-tight">Analítica del espacio</p>
                        <p class="text-xs text-indigo-100 truncate">Espacio: {{ spaceName || projectKey }}</p>
                    </div>
                    <button @click="$emit('close')" class="w-8 h-8 rounded-lg flex items-center justify-center text-indigo-100 hover:text-white hover:bg-white/15 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-5 space-y-5 custom-scrollbar">

                    <!-- Loading -->
                    <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-gray-400">
                        <svg class="w-8 h-8 animate-spin mb-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="text-sm">Calculando KPIs…</p>
                    </div>

                    <div v-else-if="errorMsg" class="px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-sm text-red-600 dark:text-red-400">
                        {{ errorMsg }}
                    </div>

                    <template v-else-if="data">
                        <!-- ══════ Panel 1: Cumplimiento de Plazos ══════ -->
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 rounded-full bg-indigo-500 shrink-0"></span>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cumplimiento de Plazos</p>
                            </div>

                            <div class="flex items-center gap-4">
                                <!-- Donut a tiempo vs tarde -->
                                <div class="relative w-32 h-32 shrink-0">
                                    <canvas ref="plazosCanvas" width="128" height="128"></canvas>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                        <span class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ data.plazos.on_time_pct }}%</span>
                                        <span class="text-[9px] text-gray-400 uppercase tracking-wide">a tiempo</span>
                                    </div>
                                </div>
                                <!-- Leyenda -->
                                <div class="flex-1 space-y-2 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                        <span class="text-gray-600 dark:text-gray-300">A tiempo</span>
                                        <span class="ml-auto font-semibold text-gray-900 dark:text-white">{{ data.plazos.on_time }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                                        <span class="text-gray-600 dark:text-gray-300">Tarde</span>
                                        <span class="ml-auto font-semibold text-gray-900 dark:text-white">{{ data.plazos.late }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stat cards -->
                            <div class="grid grid-cols-3 gap-2 mt-4">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2.5 text-center">
                                    <p class="text-[10px] text-gray-400 leading-tight mb-1">Vencidas activas</p>
                                    <p class="text-lg font-bold text-red-500">{{ data.plazos.vencidas_activas }}</p>
                                </div>
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2.5 text-center">
                                    <p class="text-[10px] text-gray-400 leading-tight mb-1">Desviación prom.</p>
                                    <p class="text-lg font-bold text-amber-500">+{{ data.plazos.desviacion_prom_dias }} d</p>
                                </div>
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2.5 text-center">
                                    <p class="text-[10px] text-gray-400 leading-tight mb-1">Lead time</p>
                                    <p class="text-lg font-bold text-indigo-500">{{ data.plazos.lead_time_dias }} d</p>
                                </div>
                            </div>
                        </section>

                        <!-- ══════ Panel: Reprogramaciones & Rankings (3 tabs) ══════ -->
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 rounded-full bg-indigo-500 shrink-0"></span>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Reprogramaciones &amp; Estabilidad</p>
                            </div>

                            <!-- Tabs -->
                            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700 mb-3">
                                <button
                                    v-for="t in rankTabs"
                                    :key="t.id"
                                    type="button"
                                    @click="activeRankTab = t.id"
                                    :class="[
                                        'px-2.5 py-1.5 text-[11px] font-semibold border-b-2 -mb-px transition-colors',
                                        activeRankTab === t.id
                                            ? 'border-current'
                                            : 'border-transparent text-gray-400 hover:text-gray-600 dark:hover:text-gray-300',
                                    ]"
                                    :style="activeRankTab === t.id ? { color: t.color } : {}"
                                >
                                    {{ t.label }}
                                </button>
                            </div>

                            <!-- Top 5 del tab activo (con protagonismo: tamaño + color del tab) -->
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: currentRankTab.color }"></span>
                                <p class="text-[13px] font-bold" :style="{ color: currentRankTab.color }">{{ currentRankTab.subtitle }}</p>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(item, index) in currentRanking" :key="item.key" class="text-xs">
                                    <!-- Nombre (truncado + tooltip) + valor -->
                                    <div class="flex items-center justify-between gap-3 mb-1">
                                        <span
                                            class="truncate font-semibold text-gray-700 dark:text-gray-200"
                                            :title="`${item.key} · ${item.summary || ''}`"
                                        >{{ item.summary || item.key }}</span>
                                        <span class="shrink-0 font-bold tabular-nums" :style="{ color: currentRankTab.color }">{{ item.value }}{{ currentRankTab.unit }}</span>
                                    </div>
                                    <!-- Barra (crece desde 0 con efecto "en vivo" al cambiar de tab) -->
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                        <div
                                            class="h-full rounded-full bar-fill"
                                            :style="{
                                                width: (barAnim ? barPct(item) : 0) + '%',
                                                backgroundColor: currentRankTab.color,
                                                transitionDelay: (index * 70) + 'ms',
                                            }"
                                        ></div>
                                    </div>
                                </div>
                                <p v-if="!currentRanking.length" class="text-xs text-gray-400 italic py-4 text-center">Sin datos en este ranking.</p>
                            </div>
                        </section>

                        <!-- ══════ Panel 2: Estados & Resultados ══════ -->
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 rounded-full bg-indigo-500 shrink-0"></span>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Estados & Resultados</p>
                            </div>

                            <div class="flex items-center gap-4">
                                <!-- Donut de estados -->
                                <div class="relative w-32 h-32 shrink-0">
                                    <canvas ref="estadosCanvas" width="128" height="128"></canvas>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                        <span class="text-xl font-extrabold text-gray-900 dark:text-white">{{ data.estados.total }}</span>
                                        <span class="text-[9px] text-gray-400 uppercase tracking-wide">total</span>
                                    </div>
                                </div>
                                <!-- Leyenda de estados -->
                                <div class="flex-1 grid grid-cols-1 gap-1 text-xs">
                                    <div v-for="(count, st) in data.estados.distribucion" :key="st" class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: statusColor(st) }"></span>
                                        <span class="text-gray-600 dark:text-gray-300 truncate">{{ st }}</span>
                                        <span class="ml-auto font-semibold text-gray-900 dark:text-white">{{ count }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stat cards -->
                            <div class="grid grid-cols-3 gap-2 mt-4">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2.5 text-center">
                                    <p class="text-[10px] text-gray-400 leading-tight mb-1">Finalización</p>
                                    <p class="text-lg font-bold text-emerald-500">{{ data.estados.tasa_finalizacion_pct }}%</p>
                                </div>
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2.5 text-center">
                                    <p class="text-[10px] text-gray-400 leading-tight mb-1">Cancelación</p>
                                    <p class="text-lg font-bold text-red-500">{{ data.estados.tasa_cancelacion_pct }}%</p>
                                </div>
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2.5 text-center">
                                    <p class="text-[10px] text-gray-400 leading-tight mb-1">Activas / Cerradas</p>
                                    <p class="text-lg font-bold text-indigo-500">{{ data.estados.activas }} / {{ data.estados.cerradas }}</p>
                                </div>
                            </div>
                        </section>

                        <p class="text-[10px] text-gray-400 text-center pt-1">
                            Métricas sobre {{ data.estados.total }} actividad(es) raíz del espacio. Deadline considerado: el vigente (última reprogramación).
                        </p>
                    </template>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';

const props = defineProps({
    show:       { type: Boolean, default: false },
    projectKey: { type: String,  default: '' },
    spaceName:  { type: String,  default: '' },
});

defineEmits(['close']);

const loading  = ref(false);
const errorMsg = ref('');
const data     = ref(null);

const plazosCanvas  = ref(null);
const estadosCanvas = ref(null);
let plazosChart  = null;
let estadosChart = null;

// Colores de estado (espejo de ProyectoDTO::mapStatus, en hex para Chart.js).
const STATUS_COLORS = {
    'Pendiente':    '#9ca3af',
    'En Curso':     '#3b82f6',
    'En Revisión':  '#60a5fa',
    'En Pausa':     '#f59e0b',
    'Reprogramado': '#d97706',
    'Finalizado':   '#10b981',
    'Cancelado':    '#ef4444',
};
const statusColor = (st) => STATUS_COLORS[st] || '#a78bfa';

// ── Tabs de rankings (Top 5) ────────────────────────────────────────────────
const rankTabs = [
    { id: 'reprogramados', label: 'Reprogramados', color: '#f59e0b', unit: '',  subtitle: 'Top 5 con más reprogramaciones' },
    { id: 'vencidos',      label: 'Vencidos',      color: '#ef4444', unit: ' d', subtitle: 'Top 5 más vencidos (días sobre la fecha límite)' },
    { id: 'terminados',    label: 'Terminados',    color: '#10b981', unit: ' d', subtitle: 'Top 5 finalizados que más tardaron (lead time)' },
];
const activeRankTab = ref('reprogramados');
const currentRankTab = computed(() => rankTabs.find((t) => t.id === activeRankTab.value) || rankTabs[0]);
const currentRanking = computed(() => data.value?.rankings?.[activeRankTab.value] || []);

// Ancho de barra proporcional al máximo del ranking actual (mínimo 8% para visibilidad).
function barPct(item) {
    const max = Math.max(...currentRanking.value.map((i) => i.value), 1);
    return Math.max(8, Math.round((item.value / max) * 100));
}

// Efecto "tráfico en vivo": al cambiar de tab, las barras vuelven a 0 y crecen
// escalonadas hasta su valor, con un leve rebote (suben y se asientan).
const barAnim = ref(false);
function triggerBarAnim() {
    barAnim.value = false;
    requestAnimationFrame(() => requestAnimationFrame(() => { barAnim.value = true; }));
}
watch(activeRankTab, triggerBarAnim);

function destroyCharts() {
    plazosChart?.destroy();  plazosChart = null;
    estadosChart?.destroy(); estadosChart = null;
}

function renderCharts() {
    destroyCharts();
    if (!data.value) return;

    // Panel 1: a tiempo vs tarde
    if (plazosCanvas.value) {
        const p = data.value.plazos;
        const sinDatos = (p.on_time + p.late) === 0;
        plazosChart = new Chart(plazosCanvas.value, {
            type: 'doughnut',
            data: {
                labels: ['A tiempo', 'Tarde'],
                datasets: [{
                    data: sinDatos ? [1] : [p.on_time, p.late],
                    backgroundColor: sinDatos ? ['#e5e7eb'] : ['#10b981', '#ef4444'],
                    borderWidth: 0,
                }],
            },
            options: { cutout: '70%', plugins: { legend: { display: false }, tooltip: { enabled: !sinDatos } }, responsive: false, maintainAspectRatio: false },
        });
    }

    // Panel 2: distribución de estados
    if (estadosCanvas.value) {
        const dist = data.value.estados.distribucion || {};
        const labels = Object.keys(dist);
        const sinDatos = labels.length === 0;
        estadosChart = new Chart(estadosCanvas.value, {
            type: 'doughnut',
            data: {
                labels: sinDatos ? ['Sin datos'] : labels,
                datasets: [{
                    data: sinDatos ? [1] : labels.map((l) => dist[l]),
                    backgroundColor: sinDatos ? ['#e5e7eb'] : labels.map((l) => statusColor(l)),
                    borderWidth: 0,
                }],
            },
            options: { cutout: '68%', plugins: { legend: { display: false }, tooltip: { enabled: !sinDatos } }, responsive: false, maintainAspectRatio: false },
        });
    }
}

async function fetchKpis() {
    if (!props.projectKey) return;
    loading.value = true;
    errorMsg.value = '';
    data.value = null;
    try {
        const { status, data: resp } = await axios.get(
            route('gestion-proyectos.analytics', { projectKey: props.projectKey }),
            { validateStatus: () => true }
        );
        if (status >= 200 && status < 300) {
            data.value = resp;
            await nextTick();
            // Doble rAF: asegura que el canvas esté en layout (drawer teleportado + animado)
            // antes de instanciar los charts; si no, Chart.js puede medir 0 y no dibujar.
            requestAnimationFrame(() => requestAnimationFrame(renderCharts));
            triggerBarAnim(); // barras crecen al cargar
        } else if (status === 403) {
            errorMsg.value = 'No tienes permiso para ver la analítica de este espacio.';
        } else {
            errorMsg.value = resp?.error || resp?.message || 'No se pudieron cargar los KPIs.';
        }
    } catch {
        errorMsg.value = 'Error de conexión al cargar los KPIs.';
    } finally {
        loading.value = false;
    }
}

watch(() => props.show, (val) => {
    // Bloquea el scroll de la página mientras el drawer está abierto (él tiene su propio scroll).
    document.body.style.overflow = val ? 'hidden' : '';
    if (val) {
        activeRankTab.value = 'reprogramados';
        fetchKpis();
    } else {
        destroyCharts();
        barAnim.value = false; // reset para re-animar en la próxima apertura
    }
});

onBeforeUnmount(() => {
    destroyCharts();
    document.body.style.overflow = ''; // por si se desmonta estando abierto
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Transición lenta del cajón (slide desde la derecha). */
.slide-drawer-enter-active, .slide-drawer-leave-active { transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1); }
.slide-drawer-enter-from, .slide-drawer-leave-to { transform: translateX(100%); }

/* Barras de ranking: crecen desde 0 con un leve rebote ("suben y se asientan"). */
.bar-fill { transition: width 0.6s cubic-bezier(0.34, 1.45, 0.64, 1); }
</style>
