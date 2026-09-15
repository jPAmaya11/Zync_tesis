<script setup>
import { ref, computed, watch, reactive, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { severityIcon } from '@/Composables/GestionProyectos/useSeverityIcon';

const props = defineProps({
    show: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'focus-issue']);

// Los filtros persisten en sessionStorage: el salto cross-espacio recarga la página
// (window.location) y sin esto el usuario perdía búsqueda/rango/orden al saltar.
const FILTROS_KEY = 'gp-mis-pendientes-filtros';
let __saved = {};
try { __saved = JSON.parse(sessionStorage.getItem(FILTROS_KEY) || '{}') || {}; } catch { /* sin estado previo */ }

const items       = ref([]);        // items acumulados (todas las páginas cargadas)
const loading     = ref(false);     // carga inicial / recarga por filtro
const loadingMore = ref(false);     // "Cargar más"
const filtro      = ref(__saved.filtro ?? 'activas'); // 'activas' | 'todas'
const desde       = ref(__saved.desde ?? '');         // rango de fechas (YYYY-MM-DD)
const hasta       = ref(__saved.hasta ?? '');
const q           = ref(__saved.q ?? '');             // búsqueda por nombre/clave (al backend)
const sort        = ref(__saved.sort ?? '');          // '' (sin orden explícito, = por fecha) | 'fecha' | 'prioridad'
const showFilters = ref(false);     // panel desplegable de "Filtros"
const page        = ref(1);
const hasMore     = ref(false);
const total       = ref(0);         // total del backend (conjunto filtrado completo)
const vencidas    = ref(0);         // vencidas del backend
const collapsed   = reactive({});

// Token de secuencia: cada load() toma un nº y solo la respuesta del load MÁS RECIENTE
// puede escribir estado. Sin esto, una respuesta lenta y vieja (filtro anterior, o una
// página de "Cargar más" cruzada con un cambio de filtro) llegaba tarde y pisaba/mezclaba
// los resultados vigentes.
let loadSeq = 0;

// Carga una página. reset=true reinicia a la página 1 (cambio de filtro); si no, acumula.
async function load(reset = true) {
    if (reset) { page.value = 1; loading.value = true; } else { loadingMore.value = true; }
    const seq = ++loadSeq;
    try {
        const { data } = await axios.get(route('gestion-proyectos.mis-pendientes'), {
            params: {
                page: page.value,
                solo_activas: filtro.value === 'activas' ? 1 : 0,
                desde: desde.value || undefined,
                hasta: hasta.value || undefined,
                q: q.value || undefined,
                sort: sort.value,
            },
        });
        if (seq !== loadSeq) return; // respuesta obsoleta: ya hay un load más nuevo
        const nuevos = Array.isArray(data.items) ? data.items : [];
        items.value    = reset ? nuevos : items.value.concat(nuevos);
        total.value    = data.total ?? items.value.length;
        vencidas.value = data.vencidas ?? 0;
        hasMore.value  = !!data.has_more;
    } catch {
        if (seq !== loadSeq) return; // error de un request obsoleto: lo gestiona el nuevo
        if (reset) {
            // Header coherente con la lista vacía (antes conservaba los contadores
            // y el "Cargar más" del filtro anterior sobre una lista en blanco).
            items.value = [];
            total.value = 0;
            vencidas.value = 0;
            hasMore.value = false;
        } else {
            // Revertir el page++ de "Cargar más": si falló la página N, el siguiente
            // intento debe volver a pedir N (antes saltaba a N+1 y se perdían 20 items).
            page.value = Math.max(1, page.value - 1);
        }
        window.showToast?.('No se pudieron cargar tus pendientes.', 'error', { timer: 2500 });
    }
    loading.value = false;
    loadingMore.value = false;
}

function loadMore() {
    if (!hasMore.value || loadingMore.value || loading.value) return;
    page.value += 1;
    load(false);
}

function limpiarFechas() {
    desde.value = '';
    hasta.value = '';
}

// Limpia TODOS los filtros del panel (orden + rango de fechas).
function limpiarFiltros() {
    desde.value = '';
    hasta.value = '';
    sort.value = '';
}

// Hay filtros aplicados (para el puntito del botón "Filtros").
const filtrosActivos = computed(() => !!desde.value || !!hasta.value || !!sort.value);

// Al abrir el panel: cargar desde la página 1. `immediate` cubre el caso de montar
// con el panel YA abierto (estado persistido tras F5): sin él no habría carga inicial.
watch(() => props.show, (v) => { if (v) load(true); }, { immediate: true });

// Cambios de filtro (toggle o rango de fechas) recargan desde la página 1.
// Cada cambio se persiste para sobrevivir el salto cross-espacio (recarga dura).
let filtroTimer = null;
watch([filtro, desde, hasta, q, sort], ([f, d, h, qq, s]) => {
    try { sessionStorage.setItem(FILTROS_KEY, JSON.stringify({ filtro: f, desde: d, hasta: h, q: qq, sort: s })); } catch { /* storage no disponible */ }
    if (!props.show) return;
    clearTimeout(filtroTimer);
    filtroTimer = setTimeout(() => load(true), 250);
});

// El contenido pesado (lista de cards) solo vive en el DOM mientras el panel está
// visible; al cerrar se desmonta TRAS la animación de 500ms (libera cientos de nodos).
const rendered = ref(props.show);
let renderTimer = null;
watch(() => props.show, (v) => {
    clearTimeout(renderTimer);
    if (v) rendered.value = true;
    else renderTimer = setTimeout(() => { rendered.value = false; }, 500);
});

onBeforeUnmount(() => { clearTimeout(filtroTimer); clearTimeout(renderTimer); });

// Recarga pública: el Index la llama tras editar una actividad inline (título, fecha,
// estado…) para que el panel refleje el cambio sin recargar la página. Solo recarga si
// está visible; si está cerrado, el watch de `show` ya recarga al reabrir.
defineExpose({ recargar: () => { if (props.show) load(true); } });

// Agrupación por espacio sobre los items ya cargados (el backend filtra, ordena y pagina).
const grupos = computed(() => {
    const por = {};
    for (const it of items.value) {
        const k = it.project || '—';
        (por[k] ??= { project: k, project_name: it.project_name || k, items: [] }).items.push(it);
    }
    const arr = Object.values(por);
    arr.sort((a, b) => String(a.project_name).localeCompare(String(b.project_name)));
    return arr;
});

function toggleGrupo(p) { collapsed[p] = !collapsed[p]; }

// ── Presentación ────────────────────────────────────────────────────────────
const TYPE = {
    actividad:      { ring: 'bg-indigo-500/15 text-indigo-500 dark:text-indigo-300',  pill: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' },
    subactividad:   { ring: 'bg-violet-500/15 text-violet-500 dark:text-violet-300',  pill: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300' },
    reprogramacion: { ring: 'bg-amber-500/15 text-amber-500 dark:text-amber-300',     pill: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' },
    tarea:          { ring: 'bg-emerald-500/15 text-emerald-500 dark:text-emerald-300', pill: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' },
};
const typeMeta = (t) => TYPE[t] || TYPE.actividad;

const ICON = {
    actividad:      'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    subactividad:   'M4 4v7a2 2 0 002 2h9m0 0l-3-3m3 3l-3 3',
    reprogramacion: 'M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.405 0a8.25 8.25 0 0013.803 3.7l3.181-3.182m0-11.667l-3.181 3.183a8.25 8.25 0 00-13.803 3.7',
    tarea:          'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
const typeIcon = (t) => ICON[t] || ICON.actividad;

function statusCls(s) {
    return {
        'Pendiente':    'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-300',
        'En Curso':     'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
        'En Revisión':  'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
        'Finalizado':   'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        'Cancelado':    'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
        'Reprogramado': 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        'En Pausa':     'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
    }[s] || 'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-300';
}

function fmtDate(ymd) {
    if (!ymd) return '';
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
}


function initials(name) {
    return (name || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}

function abrir(it) {
    // El padre (Index.vue) decide: mismo espacio → scroll+flash; otro espacio →
    // abre el sidebar de espacios, cambia al espacio y enfoca el issue.
    emit('focus-issue', it);
}
</script>

<template>
    <!-- Carril que anima su ANCHO (0 → 27rem). Al crecer empuja el contenido hermano
         del flex hacia la izquierda: la tabla se comprime con suavidad, sin overlay.
         justify-end ancla el panel a la derecha → se revela desde el borde derecho. -->
    <!-- self-stretch hace que el carril mida lo mismo que la FILA flex (= la tabla); el panel
         (absolute, max-h-full) usa esa altura como techo. Resultado: piso de ~1 pantalla en
         espacios vacíos (min-h) y crecimiento dinámico junto a tablas largas, con tope 200vh. -->
    <div
        :class="[
            'relative shrink-0 self-stretch overflow-hidden transition-[width] duration-500 ease-in-out',
            show ? 'w-[27rem] min-h-[calc(100vh-7rem)]' : 'w-0',
        ]"
    >
        <aside
            :class="[
                // Anclado ARRIBA. Altura = min(contenido, altura de la fila/tabla, 200vh):
                // en espacios vacíos queda al piso de ~1 pantalla (min-h del carril); en
                // espacios con muchas filas crece hacia abajo mostrando más pendientes.
                'absolute top-0 right-0 w-[26rem] max-h-[min(100%,200vh)] flex flex-col rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden transition-opacity duration-500',
                show ? 'opacity-100 delay-100' : 'opacity-0',
            ]"
            style="background: #5f5fff1f;"
        >
                        <!-- Header -->
                        <div class="shrink-0 px-5 pt-5 pb-3 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Mis pendientes</h2>
                                <span v-if="vencidas > 0" class="inline-flex items-center gap-1 px-2 h-[22px] rounded-full bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300 text-xs font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>{{ vencidas }} vencidas
                                </span>
                                <button @click="$emit('close')" class="ml-auto w-8 h-8 rounded-full flex items-center justify-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <!-- Toggle + contador total a la derecha -->
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <div class="inline-flex p-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-semibold">
                                    <button @click="filtro = 'activas'" :class="['px-3 py-1.5 rounded-md transition-colors', filtro === 'activas' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400']">Solo activas</button>
                                    <button @click="filtro = 'todas'" :class="['px-3 py-1.5 rounded-md transition-colors', filtro === 'todas' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400']">Todas</button>
                                </div>
                                <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-indigo-600 text-white text-xs font-bold">{{ total }}</span>
                            </div>

                            <!-- Buscador (siempre visible) + botón "Filtros" -->
                            <div class="mt-3 flex items-center gap-2">
                                <div class="relative flex-1 min-w-0">
                                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                    </svg>
                                    <input
                                        v-model="q"
                                        type="text"
                                        placeholder="Buscar pendiente…"
                                        class="w-full h-9 pl-8 pr-2 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="showFilters = !showFilters"
                                    title="Filtros y orden"
                                    :class="[
                                        'relative shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg border transition-colors',
                                        (showFilters || filtrosActivos)
                                            ? 'border-indigo-300 dark:border-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/60',
                                    ]"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                                    <span v-if="filtrosActivos && !showFilters" class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-indigo-500 ring-2 ring-white dark:ring-gray-900"></span>
                                </button>
                            </div>

                            <!-- Panel "Filtros": orden + rango de fecha límite (colapsable) -->
                            <div v-if="showFilters" class="mt-2.5 p-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30 space-y-3">
                                <!-- Ordenar por -->
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Ordenar por</span>
                                    <div class="inline-flex p-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-semibold">
                                        <button @click="sort = sort === 'fecha' ? '' : 'fecha'" :class="['px-3 py-1.5 rounded-md transition-colors', sort === 'fecha' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400']">Fecha límite</button>
                                        <button @click="sort = sort === 'prioridad' ? '' : 'prioridad'" :class="['px-3 py-1.5 rounded-md transition-colors', sort === 'prioridad' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400']">Prioridad</button>
                                    </div>
                                </div>
                                <!-- Rango de fecha límite -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                                            Fecha límite
                                        </span>
                                        <button v-if="desde || hasta" @click="limpiarFechas" class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Limpiar</button>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <label class="flex-1 min-w-0">
                                            <span class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-0.5 ml-0.5">Desde</span>
                                            <input v-model="desde" type="date" :max="hasta || undefined" class="w-full h-8 px-2 text-[11px] rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
                                        </label>
                                        <label class="flex-1 min-w-0">
                                            <span class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 mb-0.5 ml-0.5">Hasta</span>
                                            <input v-model="hasta" type="date" :min="desde || undefined" class="w-full h-8 px-2 text-[11px] rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
                                        </label>
                                    </div>
                                </div>
                                <!-- Limpiar TODOS los filtros aplicados -->
                                <button
                                    v-if="filtrosActivos"
                                    @click="limpiarFiltros"
                                    class="w-full flex items-center justify-center gap-1.5 h-8 rounded-md border border-gray-200 dark:border-gray-700 text-[11px] font-semibold text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    Limpiar filtros
                                </button>
                            </div>
                        </div>

                        <!-- Body: montado solo mientras el panel está visible (rendered se apaga
                             500ms después de cerrar, al terminar la animación del carril). -->
                        <div v-if="rendered" class="flex-1 overflow-y-auto custom-scrollbar px-4 py-3 space-y-4">
                            <!-- Loading: SOLO en la primera carga (lista aún vacía). Al re-filtrar,
                                 la lista vigente se atenúa con suavidad (abajo) en vez de un spinner. -->
                            <div v-if="loading && !items.length" class="flex items-center justify-center py-12 text-gray-400">
                                <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>

                            <!-- Vacío -->
                            <div v-else-if="!grupos.length" class="flex flex-col items-center justify-center py-16 text-center text-gray-400">
                                <svg class="w-12 h-12 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <p class="text-sm font-medium">No tienes pendientes {{ filtro === 'activas' ? 'activos' : '' }}.</p>
                            </div>

                            <!-- Grupos por espacio. Mientras se re-filtra (loading con lista en
                                 pantalla) la lista se atenúa y desliza sutilmente; al llegar los
                                 resultados vuelve a plena opacidad — transición suave, sin spinner. -->
                            <div :class="['space-y-4 transition-[opacity,transform] duration-300 ease-out', loading ? 'opacity-40 translate-y-1 pointer-events-none' : 'opacity-100 translate-y-0']">
                            <div v-for="g in grupos" :key="g.project">
                                <button @click="toggleGrupo(g.project)" class="w-full flex items-center gap-2 px-1 py-1.5 text-left">
                                    <span class="w-5 h-5 rounded-md bg-indigo-500/15 text-indigo-500 dark:text-indigo-300 inline-flex items-center justify-center text-[10px] font-bold shrink-0">{{ (g.project_name || g.project).slice(0, 2).toUpperCase() }}</span>
                                    <span class="text-sm font-bold text-gray-700 dark:text-gray-200 truncate">{{ g.project_name }}</span>
                                    <span class="text-xs text-gray-400">· {{ g.items.length }}</span>
                                    <svg class="ml-auto w-4 h-4 text-gray-400 transition-transform" :class="collapsed[g.project] ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                </button>

                                <!-- v-if (no v-show): un grupo colapsado retira sus cards del DOM -->
                                <div v-if="!collapsed[g.project]" class="space-y-2 mt-1">
                                    <button
                                        v-for="it in g.items"
                                        :key="it.key"
                                        @click="abrir(it)"
                                        :class="['w-full text-left relative rounded-xl border p-3 flex gap-3 transition-colors',
                                                 'bg-gray-50 dark:bg-gray-800/60 hover:bg-gray-100 dark:hover:bg-gray-800 border-gray-100 dark:border-gray-700/60',
                                                 it.is_overdue ? 'border-l-4 border-l-red-500' : '']"
                                    >
                                        <!-- Ícono de tipo -->
                                        <span :class="['w-9 h-9 rounded-lg inline-flex items-center justify-center shrink-0', typeMeta(it.type).ring]">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="typeIcon(it.type)" /></svg>
                                        </span>

                                        <!-- Centro -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold whitespace-nowrap', typeMeta(it.type).pill]">{{ it.key }}</span>
                                                <span :class="['ml-auto px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap', statusCls(it.status)]">{{ it.status }}</span>
                                            </div>
                                            <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100 line-clamp-2">{{ it.title }}</p>
                                            <p v-if="it.type === 'tarea' && it.parent_key" class="mt-0.5 text-[11px] text-gray-400 truncate">↳ en {{ it.parent_key }}</p>
                                            <div class="mt-1.5 flex items-center gap-2">
                                                <span v-if="it.is_overdue" class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-500">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    Vencida · {{ fmtDate(it.due_date) }}
                                                </span>
                                                <span v-else-if="it.due_date" class="text-[11px] text-gray-400">{{ fmtDate(it.due_date) }}</span>
                                                <svg v-if="it.priority" class="w-3.5 h-3.5 shrink-0" :class="severityIcon(it.priority).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(it.priority).path" /></svg>
                                            </div>
                                        </div>

                                        <!-- Avatar del creador -->
                                        <span v-if="it.creator" class="shrink-0 self-center" :title="`Creado por ${it.creator.display_name}`">
                                            <img v-if="it.creator.avatar_url" :src="it.creator.avatar_url" class="w-7 h-7 rounded-full object-cover" alt="" />
                                            <span v-else class="w-7 h-7 rounded-full bg-indigo-500 text-white text-[10px] font-bold inline-flex items-center justify-center">{{ initials(it.creator.display_name) }}</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            </div>

                            <!-- Cargar más (paginación 20 en 20) -->
                            <div v-if="hasMore && !loading" class="pt-1 pb-2 flex justify-center">
                                <button
                                    @click="loadMore"
                                    :disabled="loadingMore"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60 disabled:opacity-50 transition-colors"
                                >
                                    <svg v-if="loadingMore" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    {{ loadingMore ? 'Cargando…' : 'Cargar más' }}
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="shrink-0 px-5 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                Ver todo ({{ total }})
                            </span>
                            <button @click="load(true)" :disabled="loading" class="inline-flex items-center gap-1.5 text-indigo-600 dark:text-indigo-400 font-medium hover:underline disabled:opacity-50">
                                <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.405 0a8.25 8.25 0 0013.803 3.7l3.181-3.182m0-11.667l-3.181 3.183a8.25 8.25 0 00-13.803 3.7" /></svg>
                                Actualizar
                            </button>
                        </div>
        </aside>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
</style>
