<script setup>
import { computed, ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import IssueTableFooter from '@/Components/Modules/GestionProyectos/IssueTableFooter.vue';
import GpUserAvatar from '@/Components/Modules/GestionProyectos/GpUserAvatar.vue';
import { severityIcon } from '@/Composables/GestionProyectos/useSeverityIcon';
import { issueTypeLabel } from '@/Composables/GestionProyectos/useIssueTypeLabel';

const props = defineProps({
    groupedIssues: { type: Array, required: true },
    visibleColumns: { type: Array, required: true },
    selectedIssues: { type: Array, required: true },
    tableLoading: { type: Boolean, default: false },
    canUserWrite: { type: Boolean, default: false },
    editing: { type: Object, required: true },
    inlineLoading: { type: Boolean, default: false },
    totalCount: { type: Number, default: 0 },
    allIssuesCount: { type: Number, default: 0 },
    filteredCount:   { type: Number, default: 0 },
    isLast: { type: Boolean, default: true },
    loadMoreLoading: { type: Boolean, default: false },
    customFields: { type: Array, default: () => [] },
    jiraPriorities: { type: Array, default: () => [] },
    teamsLocal: { type: Array, default: () => [] },
    assignableUsers: { type: Array, default: () => [] },
    terminalStatuses: { type: Array, default: () => ['Finalizado'] },
    // Subactividades desplegables inline (estado controlado por el padre)
    expandedKeys: { type: Object, default: () => ({}) },
    childrenLoading: { type: Object, default: () => ({}) },
    // Reprogramaciones desplegables (estado controlado por el padre)
    expandedReprogKeys: { type: Object, default: () => ({}) },
    reprogLoading: { type: Object, default: () => ({}) },
    canApprove: { type: Boolean, default: false },
    // Gestión del espacio (propietario/administrador): única que puede eliminar subactividades.
    canManage: { type: Boolean, default: false },
    // account_id del usuario autenticado, para resaltar sutilmente sus filas asignadas.
    currentUserAccountId: { type: String, default: '' },
    // IDs de usuarios con rol "admin" en el espacio activo (administrador/propietario/admin global),
    // para pintar la insignia "Admin" en su avatar.
    spaceAdminIds: { type: Array, default: () => [] },
});

// ¿El usuario mostrado es admin del espacio activo? (para la insignia "Admin" en el avatar)
const isSpaceAdmin = (user) => {
    const id = Number(user?.account_id);
    return Number.isFinite(id) && props.spaceAdminIds.includes(id);
};

// ¿El issue (actividad o subactividad) está asignado al usuario actual? Resaltado sutil de "lo mío".
const isMine = (issue) =>
    !!props.currentUserAccountId &&
    String(issue?.assignee?.account_id ?? '') === String(props.currentUserAccountId);

// ¿La actividad está desplegada mostrando sus subactividades?
const isExpanded = (key) => !!props.expandedKeys[key];
// ¿La actividad está desplegada mostrando sus reprogramaciones?
const isReprogExpanded = (key) => !!props.expandedReprogKeys[key];

// ── Grupo: el padre y sus filas hijas (subactividades/reprogramaciones) comparten
// un fondo índigo común cuando la actividad está desplegada, para leerlos como un bloque.
// Cabecera del grupo = actividad raíz desplegada (subactividades o reprogramaciones).
const isGroupHead = (issue) =>
    !issue._isChild && !issue._isReprog && !issue._isAddRow &&
    (isExpanded(issue.key) || isReprogExpanded(issue.key));
// ¿La fila pertenece a un grupo abierto? (cabecera o cualquier fila hija/añadir)
const inOpenGroup = (issue) => isGroupHead(issue) || !!issue._grpMember;

// Color de la barra de cumplimiento de una subactividad según su % de tareas.
const cumplimientoColor = (pct) => (pct >= 100 ? 'bg-emerald-500' : pct >= 50 ? 'bg-blue-500' : 'bg-amber-500');


// Una tarea en estado terminal (ej. Finalizado) ya no admite cambio de estado.
const isStatusTerminal = (issue) => props.terminalStatuses.includes(issue?.status?.name);

const findUserById = (id) => {
    if (!id) return null;
    return props.assignableUsers.find(u => u.account_id === id);
};

const emit = defineEmits([
    'update:selectedIssues',
    'update:editingValue',
    'start-edit',
    'cancel-edit',
    'save-edit',
    'load-more',
    'open-subtask',
    'open-timeline',
    'open-history',
    'toggle-select-all',
    'toggle-expand',
    'create-subactividad',
    'delete-subactividad',
    'toggle-expand-reprog',
    'open-description',
    'open-summary',
    'copy-link',
]);

// ─── Barra de scroll horizontal superior, sincronizada con la tabla ─────────
// Permite mover el scroll horizontal sin bajar hasta el final cuando hay muchos issues.
const tableScroll = ref(null);   // contenedor real de la tabla (overflow-x-auto)
const topScrollbar = ref(null);  // barra fantasma de arriba
const contentWidth = ref(0);     // ancho real del contenido (scrollWidth de la tabla)
const hasOverflow = computed(() => contentWidth.value > 0);
let syncingScroll = false;
let resizeObserver = null;

function syncFromTop() {
    if (syncingScroll || !tableScroll.value) return;
    syncingScroll = true;
    tableScroll.value.scrollLeft = topScrollbar.value.scrollLeft;
    requestAnimationFrame(() => { syncingScroll = false; });
}
function syncFromTable() {
    if (syncingScroll || !topScrollbar.value) return;
    syncingScroll = true;
    topScrollbar.value.scrollLeft = tableScroll.value.scrollLeft;
    requestAnimationFrame(() => { syncingScroll = false; });
}
function measure() {
    if (!tableScroll.value) return;
    // Solo mostramos la barra superior si realmente hay desbordamiento horizontal.
    const sw = tableScroll.value.scrollWidth;
    contentWidth.value = sw > tableScroll.value.clientWidth ? sw : 0;
}

onMounted(() => {
    nextTick(measure);
    if (typeof ResizeObserver !== 'undefined' && tableScroll.value) {
        resizeObserver = new ResizeObserver(() => measure());
        resizeObserver.observe(tableScroll.value);
    }
});
onBeforeUnmount(() => { if (resizeObserver) resizeObserver.disconnect(); });

// Re-medir cuando cambian los issues o las columnas visibles.
watch(() => [props.groupedIssues, props.visibleColumns], () => nextTick(measure), { deep: true });

// ─── Resize manual de la columna "Actividad" (summary) ──────────────────────
// Arrastrando la agarradera del borde derecho del header se ensancha toda la
// columna: la tabla es table-fixed, así que el ancho del <th> gobierna también
// los <td> de esa columna en todas las filas. Solo permite agrandar, nunca
// por debajo del ancho por defecto ni por encima del máximo (ambos ajustables aquí).
const DEFAULT_SUMMARY_WIDTH = 480;
const MAX_SUMMARY_WIDTH = 1100;
const summaryColWidth = ref(DEFAULT_SUMMARY_WIDTH);
let resizingSummary = false;
let resizeStartX = 0;
let resizeStartWidth = 0;

function startSummaryResize(e) {
    resizingSummary = true;
    resizeStartX = e.clientX;
    resizeStartWidth = summaryColWidth.value;
    document.body.style.cursor = 'grabbing';
    document.body.style.userSelect = 'none';
    document.addEventListener('mousemove', onSummaryResize);
    document.addEventListener('mouseup', stopSummaryResize);
    e.preventDefault();
}
function onSummaryResize(e) {
    if (!resizingSummary) return;
    const delta = e.clientX - resizeStartX;
    const next = resizeStartWidth + delta;
    summaryColWidth.value = Math.min(MAX_SUMMARY_WIDTH, Math.max(DEFAULT_SUMMARY_WIDTH, next));
}
function stopSummaryResize() {
    resizingSummary = false;
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    document.removeEventListener('mousemove', onSummaryResize);
    document.removeEventListener('mouseup', stopSummaryResize);
}
onBeforeUnmount(() => stopSummaryResize());
function colStyle(key) {
    return key === 'summary' ? { width: summaryColWidth.value + 'px' } : {};
}
// El ancho de la columna cambia el scrollWidth real de la tabla: re-medimos la barra superior.
watch(summaryColWidth, () => nextTick(measure));

// Auto-resize del textarea de edición inline del summary.
function fitSummaryTextarea(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}
function onSummaryInput(e) {
    emit('update:editingValue', e.target.value);
    fitSummaryTextarea(e.target);
}

// Helpers visuales
const initials = (name) =>
    (name || '')
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);

const getLabelColor = (label) => {
    const colors = [
        { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-800 dark:text-blue-200', border: 'border-blue-300 dark:border-blue-500/50' },
        { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-800 dark:text-green-200', border: 'border-green-300 dark:border-green-500/50' },
        { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-800 dark:text-purple-200', border: 'border-purple-300 dark:border-purple-500/50' },
        { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-800 dark:text-amber-200', border: 'border-amber-300 dark:border-amber-500/50' },
        { bg: 'bg-rose-100 dark:bg-rose-900/30', text: 'text-rose-800 dark:text-rose-200', border: 'border-rose-300 dark:border-rose-500/50' },
        { bg: 'bg-teal-100 dark:bg-teal-900/30', text: 'text-teal-800 dark:text-teal-200', border: 'border-teal-300 dark:border-teal-500/50' },
        { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-800 dark:text-orange-200', border: 'border-orange-300 dark:border-orange-500/50' },
        { bg: 'bg-fuchsia-100 dark:bg-fuchsia-900/30', text: 'text-fuchsia-800 dark:text-fuchsia-200', border: 'border-fuchsia-300 dark:border-fuchsia-500/50' },
        { bg: 'bg-pink-100 dark:bg-pink-900/30', text: 'text-pink-800 dark:text-pink-200', border: 'border-pink-300 dark:border-pink-500/50' },
        { bg: 'bg-cyan-100 dark:bg-cyan-900/30', text: 'text-cyan-800 dark:text-cyan-200', border: 'border-cyan-300 dark:border-cyan-500/50' },
        { bg: 'bg-indigo-100 dark:bg-indigo-900/30', text: 'text-indigo-800 dark:text-indigo-200', border: 'border-indigo-300 dark:border-indigo-500/50' },
    ];
    let hash = 0;
    for (let i = 0; i < label.length; i++) {
        hash = label.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % colors.length;
    const c = colors[index];
    return `${c.bg} ${c.text} ${c.border}`;
};

function issueTypeBadgeClass(type) {
    const name = type?.name?.toLowerCase() || '';
    if (name.includes('bug') || name.includes('error'))
        return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-700';
    if (name.includes('task') || name.includes('tarea'))
        return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-700';
    if (name.includes('epic') || name.includes('épica'))
        return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-700';
    return 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-900/30 dark:text-gray-400 dark:border-gray-700';
}

function getColumnWidthClass(key) {
    switch (key) {
        case 'issue_type': return 'w-24';
        case 'summary':    return ''; // ancho dinámico vía colStyle()/summaryColWidth (resize manual)
        case 'status':     return 'w-40';
        case 'assignee':   return 'w-48';
        case 'reporter':   return 'w-44';
        case 'priority':   return 'w-32';
        case 'created_at':      return 'w-32';
        case 'start_date':      return 'w-32';
        case 'fecha_limite':    return 'w-32';
        case 'fecha_entrega':   return 'w-32';
        case 'fecha_aprobacion':return 'w-32';
        case 'updated_at':      return 'w-32';
        case 'dias_estimados':  return 'w-20';
        case 'description':  return 'w-[280px]';
        default:             return 'w-40';
    }
}

// Abreviaciones para headers de columnas cuyo texto completo supera el ancho w-40 (160px)
// y fuerzan la columna a expandirse por el whitespace-nowrap del <th>.
// El nombre completo sigue visible en el tooltip (title).
const COL_SHORT_LABELS = {
    fecha_entrega:    'Staging', // decisión UX: se muestra "Staging" (el campo BD sigue siendo fecha_entrega)
    fecha_aprobacion: 'Producción',
    updated_at:       'Actualización',
    created_at:       'Creada',
    dias_estimados:   'Días',
};
function colHeaderLabel(col) {
    return COL_SHORT_LABELS[col.key] ?? col.name;
}

// La columna "Seguimiento" (botones de acción) va justo después de TIPO (issue_type).
// Si TIPO está oculta por preferencias, cae al final de la fila para no perder los botones.
// Formatea fechas para MOSTRAR de forma consistente ("10 jun. 2026"), igual que created_at.
// Solo transforma strings YYYY-MM-DD; cualquier otro valor (ya formateado, número, vacío) pasa igual.
const MESES_ABBR = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
function fmtDate(v) {
    if (v === null || v === undefined || v === '') return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v));
    if (!m) return v;
    return `${parseInt(m[3], 10)} ${MESES_ABBR[parseInt(m[2], 10) - 1]} ${m[1]}`;
}

const hasIssueTypeCol = computed(() => props.visibleColumns.some((c) => c.key === 'issue_type'));
function seguimientoGoesAfter(col, idx) {
    return hasIssueTypeCol.value
        ? col.key === 'issue_type'
        : idx === props.visibleColumns.length - 1;
}

const allVisibleSelected = computed(() => {
    const allVisibleKeys = props.groupedIssues.flatMap(g => g.items.map(i => i.key));
    return allVisibleKeys.length > 0 && allVisibleKeys.every(k => props.selectedIssues.includes(k));
});

const someSelected = computed(() => {
    const allVisibleKeys = props.groupedIssues.flatMap(g => g.items.map(i => i.key));
    return props.selectedIssues.length > 0 && !allVisibleSelected.value;
});

const internalSelectedIssues = computed({
    get: () => props.selectedIssues,
    set: (val) => emit('update:selectedIssues', val)
});

</script>

<template>
    <div class="relative rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <!-- Overlay de carga no bloqueante -->
        <div v-if="tableLoading" class="absolute inset-0 z-10 bg-white/70 dark:bg-gray-800/70 flex items-center justify-center rounded-xl pointer-events-none">
            <div class="flex items-center gap-2 text-sm text-indigo-600 dark:text-indigo-400 font-medium">
                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Cargando...
            </div>
        </div>

        <!-- Barra de scroll horizontal superior (sincronizada). Solo si hay desbordamiento. -->
        <div
            v-show="hasOverflow"
            ref="topScrollbar"
            @scroll="syncFromTop"
            class="overflow-x-auto overflow-y-hidden border-b border-gray-100 dark:border-gray-700/50 custom-scrollbar"
        >
            <div :style="{ width: contentWidth + 'px', height: '1px' }"></div>
        </div>

        <!-- max-h + overflow-y: el scroll vertical ocurre AQUÍ dentro, así el thead
             sticky (top-0) se queda fijo aunque haya muchísimos registros. El scroll
             horizontal sigue sincronizado con la barra superior. -->
        <div ref="tableScroll" @scroll="syncFromTable" class="overflow-x-auto overflow-y-auto custom-scrollbar max-h-[75vh]">
            <table class="min-w-full w-full text-sm table-fixed">
                <thead class="sticky top-0 z-20 bg-gradient-to-b from-indigo-600 to-indigo-700 dark:from-gray-800 dark:to-gray-900 shadow-sm">
                    <tr class="text-left">
                        <th class="w-10 px-4 py-5 border-r border-white/15 dark:border-gray-700/60">
                            <input
                                type="checkbox"
                                :checked="allVisibleSelected"
                                :indeterminate="someSelected"
                                @change="$emit('toggle-select-all')"
                                class="w-4 h-4 rounded border-white/40 text-indigo-600 focus:ring-2 focus:ring-white/40 focus:ring-offset-0 bg-white/10 cursor-pointer"
                            />
                        </th>
                        <template v-for="(col, idx) in visibleColumns" :key="col.key">
                            <th
                                :class="[
                                    'group relative px-4 py-5 text-[12px] font-bold uppercase tracking-wider text-white whitespace-nowrap border-r border-white/10 dark:border-gray-700/40 last:border-r-0',
                                    getColumnWidthClass(col.key)
                                ]"
                                :style="colStyle(col.key)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="opacity-90 group-hover:opacity-100 transition-opacity" :title="col.name">{{ colHeaderLabel(col) }}</span>
                                </span>
                                <!-- Agarradera de resize: arrastrar ensancha la columna "Actividad" (mínimo, el ancho por defecto).
                                     Ícono de "grip" (6 puntitos, 2 columnas x 3 filas) siempre visible a baja opacidad
                                     para que se note que es arrastrable, y se resalta al pasar el mouse. -->
                                <div
                                    v-if="col.key === 'summary'"
                                    @mousedown="startSummaryResize"
                                    title="Arrastra para ensanchar la columna"
                                    class="absolute top-0 right-0 h-full w-3 cursor-grab active:cursor-grabbing select-none flex items-center justify-center hover:bg-white/20 active:bg-white/30 transition-colors"
                                >
                                    <span class="grid grid-cols-2 gap-[3px]">
                                        <span v-for="n in 6" :key="n" class="w-[3px] h-[3px] rounded-full bg-white/60 group-hover:bg-white/90 transition-colors"></span>
                                    </span>
                                </div>
                            </th>
                            <th
                                v-if="seguimientoGoesAfter(col, idx)"
                                class="w-48 px-3 py-5 text-[12px] font-bold uppercase tracking-wider text-white whitespace-nowrap text-center border-r border-white/10 dark:border-gray-700/40"
                            >
                                Seguimiento
                            </th>
                        </template>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <template v-for="group in groupedIssues" :key="group.key">
                        <tr v-if="group.label" class="bg-gray-50/70 dark:bg-gray-900/40">
                            <td :colspan="2 + visibleColumns.length" class="px-4 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                {{ group.label }}
                                <span class="ml-2 text-gray-400 normal-case">({{ group.items.length }})</span>
                            </td>
                        </tr>

                        <template v-for="issue in group.items" :key="`${group.key}-${issue.id}`">

                        <!-- Fila "+ Agregar subactividad" (cierre del bloque desplegado) -->
                        <tr v-if="issue._isAddRow" class="bg-indigo-50 dark:bg-indigo-500/[0.13]">
                            <td class="w-10 border-r border-gray-200 dark:border-gray-700"></td>
                            <td :colspan="1 + visibleColumns.length" class="px-4 py-2 pl-44">
                                <button
                                    v-if="canUserWrite"
                                    @click="$emit('create-subactividad', issue._parentKey)"
                                    class="inline-flex items-center gap-1.5 text-[12px] font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                    Agregar subactividad
                                </button>
                                <span v-else class="text-xs text-gray-400 italic">Sin subactividades.</span>
                            </td>
                        </tr>

                        <!-- Fila de actividad (padre) o subactividad (hija, indentada) -->
                        <tr
                            v-else
                            :data-issue-key="issue.key"
                            :class="[
                                'transition-colors duration-75',
                                // Precedencia: seleccionado > reprogramación (ámbar) > cabecera del grupo (índigo más fuerte)
                                //              > fila hija del grupo (índigo suave) > hover.
                                internalSelectedIssues.includes(issue.key)
                                    ? 'bg-indigo-100 dark:bg-indigo-900/30'
                                    : issue._isReprog
                                    ? 'bg-amber-50/60 dark:bg-amber-500/[0.25] hover:bg-amber-100/60 dark:hover:bg-amber-500/[0.32]'
                                    : isGroupHead(issue)
                                    ? 'bg-indigo-200/70 dark:bg-indigo-500/[0.5] hover:bg-indigo-200 dark:hover:bg-indigo-500/[0.6]'
                                    : issue._grpMember
                                    ? 'bg-indigo-50 dark:bg-indigo-500/[0.13] hover:bg-indigo-100/70 dark:hover:bg-indigo-500/[0.18]'
                                    : 'hover:bg-indigo-50 dark:hover:bg-indigo-500/5',
                            ]"
                        >
                            <td class="w-10 px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                                <input
                                    v-if="!issue._isChild"
                                    type="checkbox"
                                    :value="issue.key"
                                    v-model="internalSelectedIssues"
                                    class="w-4 h-4 rounded-sm border-gray-300 dark:border-gray-600 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0 bg-white dark:bg-gray-700 cursor-pointer"
                                />
                            </td>

                            <template v-for="(col, idx) in visibleColumns" :key="col.key">
                                <td :class="[
                                    'px-4 py-3',
                                    col.key === 'summary' && editing.key === issue.key && editing.field === 'summary'
                                        ? 'overflow-visible relative z-[55]'
                                        : 'overflow-hidden',
                                    getColumnWidthClass(col.key)
                                ]" :style="colStyle(col.key)">
                                    <!-- issue_type — oculto en filas de reprogramación (-Rn) por UX -->
                                    <template v-if="col.key === 'issue_type'">
                                        <div :class="issue._isChild ? 'pl-8' : ''">
                                            <div v-if="issue.issue_type && !issue._isReprog" :class="['inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] font-medium border shadow-sm', issueTypeBadgeClass(issue.issue_type)]" :title="issueTypeLabel(issue.issue_type.name)">
                                                <img v-if="issue.issue_type.icon_url" :src="issue.issue_type.icon_url" class="w-4 h-4 shrink-0 rounded-sm" :alt="issueTypeLabel(issue.issue_type.name)" />
                                                <span class="hidden xl:inline max-w-[100px] truncate">{{ issueTypeLabel(issue.issue_type.name) }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- summary -->
                                    <template v-else-if="col.key === 'summary'">
                                        <div class="flex items-start gap-2 group" :class="issue._isChild ? 'pl-12' : issue._isReprog ? (issue._underChild ? 'pl-12' : 'pl-8') : ''">
                                            <span v-if="issue._isChild" class="mt-0.5 shrink-0 text-violet-400 dark:text-violet-400/80 text-lg leading-none select-none">↳</span>
                                            <span v-if="issue._isReprog" class="mt-0.5 shrink-0 text-amber-400 dark:text-amber-400/80 text-lg leading-none select-none">↻</span>

                                            <span
                                                @click.stop="$emit('copy-link', issue)"
                                                title="Copiar enlace para compartir este ítem"
                                                class="px-2.5 py-1 text-[11px] font-bold rounded whitespace-nowrap cursor-pointer transition hover:ring-2 hover:ring-indigo-300 dark:hover:ring-indigo-500/50 active:scale-95"
                                                :class="issue._isChild ? 'bg-violet-50 dark:bg-violet-500/20 text-violet-600 dark:text-violet-300' : issue._isReprog ? 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300' : 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300'"
                                            >{{ issue.key }}</span>
                                            <!-- Badge R{n}: en -Rn de subactividad se omite (la clave ya termina en -R{n}) para dejar espacio al nombre en la columna fija de 300px. -->
                                            <span v-if="issue._isReprog && !issue._underChild" class="inline-flex items-center justify-center h-4 px-1.5 rounded text-[9px] font-bold bg-amber-500 text-white flex-shrink-0">R{{ issue.reprogramacion_n }}</span>
                                            <div class="flex-1 min-w-0">
                                                <div v-if="editing.key === issue.key && editing.field === 'summary'" class="w-full relative">
                                                    <!-- Backdrop: clic fuera guarda -->
                                                    <div @click="$emit('save-edit')" class="fixed inset-0 z-40"></div>
                                                    <!-- Fantasma invisible: mantiene la altura de la fila sin mover nada -->
                                                    <div class="invisible whitespace-pre-wrap break-words text-sm leading-relaxed min-h-[2rem] pointer-events-none select-none" aria-hidden="true">{{ editing.value || ' ' }}</div>
                                                    <!-- Textarea flotante: absolute, sale por encima de columnas vecinas, auto-height -->
                                                    <textarea
                                                        :ref="el => el && nextTick(() => fitSummaryTextarea(el))"
                                                        :value="editing.value"
                                                        @input="onSummaryInput"
                                                        @keydown.enter.exact.prevent="$emit('save-edit')"
                                                        @keyup.esc="$emit('cancel-edit')"
                                                        :data-editing="`summary-${issue.key}`"
                                                        rows="1"
                                                        :style="{ width: summaryColWidth + 'px' }"
                                                        class="absolute top-0 left-0 z-[60] text-sm px-3 py-2 rounded-lg border border-indigo-400 shadow-2xl bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-indigo-500/50 leading-relaxed"
                                                    ></textarea>
                                                </div>
                                                <div v-else @click.stop="$emit('open-summary', issue)" class="cursor-pointer group/cell flex items-start gap-1" title="Ver título completo, editarlo y su histórico">
                                                    <span class="text-gray-800 dark:text-gray-100 line-clamp-2">{{ issue.summary }}</span>
                                                </div>
                                                <!-- Cumplimiento de tareas (solo subactividades) -->
                                                <!-- <div v-if="issue._isChild && issue.cumplimiento && issue.cumplimiento.tareas_total > 0" class="mt-1 flex items-center gap-1.5">
                                                    <div class="w-20 h-1 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                                        <div class="h-full rounded-full transition-all" :class="cumplimientoColor(issue.cumplimiento.pct)" :style="{ width: issue.cumplimiento.pct + '%' }"></div>
                                                    </div>
                                                    <span class="text-[10px] text-gray-400">{{ issue.cumplimiento.tareas_done }}/{{ issue.cumplimiento.tareas_total }} tareas</span>
                                                </div> -->
                                            </div>
                                        </div>
                                    </template>

                                    <!-- status -->
                                    <template v-else-if="col.key === 'status'">
                                        <div v-if="editing.key === issue.key && editing.field === 'status'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <div class="relative z-50 inline-flex items-center px-2 py-0.5 rounded-md text-[12px] leading-tight font-semibold uppercase tracking-wide border shadow-sm" :class="issue.status.color_class">
                                                {{ issue.status.name }}
                                                <svg class="ml-1 w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <!-- Terminal (ej. Finalizado): badge fijo, sin opciones de cambio -->
                                        <span
                                            v-else-if="isStatusTerminal(issue)"
                                            :class="['inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[12px] leading-tight font-semibold uppercase tracking-wide border cursor-default', issue.status.color_class]"
                                            title="Estado final: no admite más cambios"
                                        >
                                            {{ issue.status.name }}
                                        </span>
                                        <span
                                            v-else
                                            @click="$emit('start-edit', issue, 'status', $event)"
                                            :class="['inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[12px] leading-tight font-semibold uppercase tracking-wide border cursor-pointer group/cell transition-colors duration-150 hover:brightness-110', issue.status.color_class]"
                                        >
                                            {{ issue.status.name }}
                                        </span>
                                    </template>

                                    <!-- assignee (usuario O equipo, exclusivo) -->
                                    <template v-else-if="col.key === 'assignee'">
                                        <div v-if="editing.key === issue.key && editing.field === 'assignee'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <div class="relative z-50 flex items-center gap-2 cursor-default">
                                                <template v-if="issue.assignee">
                                                    <img v-if="issue.assignee?.avatar_url" :src="issue.assignee.avatar_url" class="w-6 h-6 rounded-full object-cover aspect-square shrink-0" />
                                                    <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ initials(issue.assignee.display_name) }}</span>
                                                    <span class="text-gray-700 dark:text-gray-200 font-medium">{{ issue.assignee.display_name }}</span>
                                                </template>
                                                <span v-else-if="issue.team" class="px-2 py-0.5 text-[11px] font-semibold rounded border border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-700 dark:bg-violet-900/30 dark:text-violet-300 inline-flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                    {{ issue.team.name }}
                                                </span>
                                                <span v-else class="text-xs text-gray-400 italic">Sin asignar</span>
                                                <svg class="ml-auto w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'assignee', $event)" class="flex items-center gap-2 cursor-pointer group/cell rounded -mx-1 px-1 hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10 transition-colors duration-150">
                                            <template v-if="issue.assignee">
                                                <GpUserAvatar :user="issue.assignee" :admin="isSpaceAdmin(issue.assignee)" />
                                                <span class="text-gray-700 dark:text-gray-200 font-medium">{{ issue.assignee.display_name }}</span>
                                            </template>
                                            <span v-else-if="issue.team" class="px-2 py-0.5 text-[11px] font-semibold rounded border border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-700 dark:bg-violet-900/30 dark:text-violet-300 inline-flex items-center gap-1 whitespace-nowrap">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                {{ issue.team.name }}
                                            </span>
                                            <span v-else class="text-xs text-gray-400 italic">Sin asignar</span>
                                        </div>
                                    </template>

                                    <!-- reporter -->
                                    <template v-else-if="col.key === 'reporter'">
                                        <div v-if="editing.key === issue.key && editing.field === 'reporter'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <div class="relative z-50 flex items-center gap-2 min-w-0 overflow-hidden cursor-default">
                                                <img v-if="issue.reporter?.avatar_url" :src="issue.reporter.avatar_url" class="w-6 h-6 rounded-full object-cover aspect-square shrink-0" />
                                                <span v-else-if="issue.reporter" class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ initials(issue.reporter.display_name) }}</span>
                                                <span v-if="issue.reporter" class="text-gray-700 dark:text-gray-200 font-medium truncate">{{ issue.reporter.display_name }}</span>
                                                <span v-else class="text-xs text-gray-400 italic">Sin informador</span>
                                                <svg class="ml-auto w-3 h-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'reporter', $event)" class="flex items-center gap-2 min-w-0 cursor-pointer group/cell rounded -mx-1 px-1 hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10 transition-colors duration-150">
                                            <GpUserAvatar v-if="issue.reporter" :user="issue.reporter" :admin="isSpaceAdmin(issue.reporter)" />
                                            <span v-if="issue.reporter" class="text-gray-700 dark:text-gray-200 font-medium truncate">{{ issue.reporter.display_name }}</span>
                                            <span v-else class="text-xs text-gray-400 italic">Sin informador</span>
                                        </div>
                                    </template>

                                    <!-- creator -->
                                    <template v-else-if="col.key === 'creator'">
                                        <div v-if="issue.creator" class="flex items-center gap-2">
                                            <GpUserAvatar :user="issue.creator" :admin="isSpaceAdmin(issue.creator)" fallback="bg-gray-400" />
                                            <span class="text-gray-700 dark:text-gray-200">{{ issue.creator.display_name }}</span>
                                        </div>
                                        <span v-else class="text-xs text-gray-400 italic">—</span>
                                    </template>

                                    <!-- aprobado_por: usuario que finalizó la actividad/subactividad/-Rn (con avatar). Vacío si aún no se finaliza. -->
                                    <template v-else-if="col.key === 'aprobado_por'">
                                        <div v-if="issue.aprobado_por" class="flex items-center gap-2">
                                            <GpUserAvatar :user="issue.aprobado_por" :admin="isSpaceAdmin(issue.aprobado_por)" fallback="bg-emerald-600" />
                                            <span class="text-gray-700 dark:text-gray-200">{{ issue.aprobado_por.display_name }}</span>
                                        </div>
                                        <span v-else class="text-xs text-gray-400 italic">—</span>
                                    </template>

                                    <!-- validado_por: valida el "aprobado por". Editable (dropdown de miembros) SOLO por
                                         aprobadores/admin y SOLO cuando la actividad está Finalizada. Si no, solo lectura. -->
                                    <template v-else-if="col.key === 'validado_por'">
                                        <div
                                            v-if="canApprove && issue.status?.name === 'Finalizado'"
                                            @click="$emit('start-edit', issue, 'validado_por', $event)"
                                            class="flex items-center gap-2 min-w-0 cursor-pointer group/cell rounded -mx-1 px-1 hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10 transition-colors duration-150"
                                        >
                                            <GpUserAvatar v-if="issue.validado_por" :user="issue.validado_por" :admin="isSpaceAdmin(issue.validado_por)" fallback="bg-sky-600" />
                                            <span v-if="issue.validado_por" class="text-gray-700 dark:text-gray-200 truncate">{{ issue.validado_por.display_name }}</span>
                                            <span v-else class="text-xs text-gray-400 italic">Sin validar</span>
                                        </div>
                                        <div v-else class="flex items-center gap-2 min-w-0">
                                            <GpUserAvatar v-if="issue.validado_por" :user="issue.validado_por" :admin="isSpaceAdmin(issue.validado_por)" fallback="bg-sky-600" />
                                            <span v-if="issue.validado_por" class="text-gray-700 dark:text-gray-200 truncate">{{ issue.validado_por.display_name }}</span>
                                            <span v-else class="text-xs text-gray-400 italic">—</span>
                                        </div>
                                    </template>

                                    <!-- solicitado_por -->
                                    <template v-else-if="col.key === 'solicitado_por'">
                                        <div v-if="editing.key === issue.key && editing.field === 'solicitado_por'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <div class="relative z-50 flex items-center gap-2 min-w-0 overflow-hidden cursor-default">
                                                <img v-if="issue.solicitado_por?.avatar_url" :src="issue.solicitado_por.avatar_url" class="w-6 h-6 rounded-full object-cover aspect-square shrink-0" />
                                                <span v-else-if="issue.solicitado_por" class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ initials(issue.solicitado_por.display_name) }}</span>
                                                <span v-if="issue.solicitado_por" class="text-gray-700 dark:text-gray-200 font-medium truncate">{{ issue.solicitado_por.display_name }}</span>
                                                <span v-else class="text-xs text-gray-400 italic">Sin solicitante</span>
                                                <svg class="ml-auto w-3 h-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'solicitado_por', $event)" class="flex items-center gap-2 min-w-0 cursor-pointer group/cell rounded -mx-1 px-1 hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10 transition-colors duration-150">
                                            <GpUserAvatar v-if="issue.solicitado_por" :user="issue.solicitado_por" :admin="isSpaceAdmin(issue.solicitado_por)" />
                                            <span v-if="issue.solicitado_por" class="text-gray-700 dark:text-gray-200 font-medium truncate">{{ issue.solicitado_por.display_name }}</span>
                                            <span v-else class="text-xs text-gray-400 italic">Sin solicitante</span>
                                        </div>
                                    </template>

                                    <!-- categoria: texto libre con catálogo por espacio (misma filosofía que las
                                         etiquetas: crece solo). El combo "elegir o escribir" vive en
                                         InlineEditDropdowns.vue, teleportado y anclado a esta celda. -->
                                    <template v-else-if="col.key === 'categoria'">
                                        <div v-if="editing.key === issue.key && editing.field === 'categoria'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <div class="relative z-50 flex items-center gap-2 min-w-0 overflow-hidden cursor-default">
                                                <span v-if="issue.categoria" class="px-2 py-0.5 text-[11px] font-semibold rounded-sm border border-indigo-500 bg-indigo-500 text-white dark:border-indigo-600 dark:bg-indigo-600 dark:text-white whitespace-nowrap truncate">{{ issue.categoria }}</span>
                                                <span v-else class="text-xs text-gray-400 italic">Sin categoría</span>
                                                <svg class="ml-auto w-3 h-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'categoria', $event)" class="flex items-center gap-2 min-w-0 cursor-pointer group/cell rounded -mx-1 px-1 hover:bg-indigo-50/60 dark:hover:bg-indigo-500/10 transition-colors duration-150">
                                            <span v-if="issue.categoria" class="px-2 py-0.5 text-[11px] font-semibold rounded-sm border border-indigo-500 bg-indigo-500 text-white dark:border-indigo-600 dark:bg-indigo-600 dark:text-white whitespace-nowrap truncate max-w-[150px]" :title="issue.categoria">{{ issue.categoria }}</span>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </div>
                                    </template>

                                    <!-- priority -->
                                    <template v-else-if="col.key === 'priority'">
                                        <div v-if="editing.key === issue.key && editing.field === 'priority'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <div class="relative z-50 flex items-center gap-2">
                                                <svg v-if="editing.value" class="w-4 h-4 shrink-0" :class="severityIcon(editing.value).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(editing.value).path" /></svg>
                                                <span class="text-gray-700 dark:text-gray-200 text-xs font-medium">{{ editing.value }}</span>
                                            </div>
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'priority', $event)" class="flex items-center gap-2 cursor-pointer group/cell">
                                            <svg v-if="issue.priority?.name" class="w-4 h-4 shrink-0" :class="severityIcon(issue.priority.name).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(issue.priority.name).path" /></svg>
                                            <span class="text-gray-700 dark:text-gray-200">{{ issue.priority?.name }}</span>
                                        </div>
                                    </template>

                                    <!-- software -->
                                    <template v-else-if="col.key === 'software'">
                                        <div v-if="editing.key === issue.key && editing.field === 'software'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <input
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                :data-editing="`software-${issue.key}`"
                                                class="relative z-50 w-full text-sm p-0 m-0 border-none ring-0 focus:ring-0 focus:outline-none outline-none bg-transparent text-gray-700 dark:text-gray-200"
                                            />
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'software')" class="cursor-pointer group/cell flex items-center gap-2">
                                            <span v-if="issue.software" class="px-2 py-0.5 text-[11px] font-semibold rounded border border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300 whitespace-nowrap inline-block">{{ issue.software }}</span>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </div>
                                    </template>

                                    <!-- entorno -->
                                    <template v-else-if="col.key === 'entorno'">
                                        <div v-if="editing.key === issue.key && editing.field === 'entorno'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <input
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                :data-editing="`entorno-${issue.key}`"
                                                class="relative z-50 w-full text-sm p-0 m-0 border-none ring-0 focus:ring-0 focus:outline-none outline-none bg-transparent text-gray-700 dark:text-gray-200"
                                            />
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'entorno')" class="cursor-pointer group/cell flex items-center gap-2">
                                            <span v-if="issue.entorno" class="px-2 py-0.5 text-[11px] font-semibold rounded border border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-700 dark:bg-teal-900/30 dark:text-teal-300 whitespace-nowrap inline-block">{{ issue.entorno }}</span>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </div>
                                    </template>

                                    <!-- description: clic abre modal centrado con el texto completo
                                         (aplica a actividad, subactividad y reprogramaciones -Rn). -->
                                    <template v-else-if="col.key === 'description'">
                                        <p
                                            v-if="issue.description"
                                            @click.stop="$emit('open-description', issue)"
                                            class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 whitespace-pre-line cursor-pointer hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                            title="Ver descripción completa"
                                        >{{ issue.description }}</p>
                                        <span v-else class="text-xs text-gray-400 italic">—</span>
                                    </template>

                                    <!-- equipo -->
                                    <template v-else-if="col.key === 'equipo'">
                                        <div class="flex items-center gap-2">
                                            <span v-if="issue.team" class="px-2 py-0.5 text-[11px] font-semibold rounded border border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-700 dark:bg-violet-900/30 dark:text-violet-300 whitespace-nowrap inline-block">{{ issue.team.name }}</span>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </div>
                                    </template>

                                    <!-- impacto -->
                                    <template v-else-if="col.key === 'impacto'">
                                        <div v-if="issue.impacto?.length" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <span v-for="tag in issue.impacto" :key="tag" class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                                <svg class="w-4 h-4 shrink-0" :class="severityIcon(tag).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(tag).path" /></svg>
                                                {{ tag }}
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </template>

                                    <!-- area_negocios -->
                                    <template v-else-if="col.key === 'area_negocios'">
                                        <span class="text-gray-700 dark:text-gray-200 text-sm">{{ issue.area_negocios || '—' }}</span>
                                    </template>

                                    <!-- periodo -->
                                    <template v-else-if="col.key === 'periodo'">
                                        <span class="text-gray-700 dark:text-gray-200 text-sm">{{ issue.periodo || '—' }}</span>
                                    </template>

                                    <!-- labels -->
                                    <template v-else-if="col.key === 'labels'">
                                        <div v-if="issue.labels?.length" class="flex items-center gap-1 flex-nowrap overflow-hidden max-w-[180px]">
                                            <span
                                                v-for="label in issue.labels.slice(0, 2)"
                                                :key="label"
                                                :title="label"
                                                :class="['px-1.5 py-0.5 text-[10px] font-semibold rounded border whitespace-nowrap truncate max-w-[80px]', getLabelColor(label)]"
                                            >{{ label }}</span>
                                            <span
                                                v-if="issue.labels.length > 2"
                                                :title="issue.labels.slice(2).join(', ')"
                                                class="flex-shrink-0 px-1.5 py-0.5 text-[10px] font-bold rounded border bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 whitespace-nowrap cursor-default"
                                            >+{{ issue.labels.length - 2 }}</span>
                                        </div>
                                        <span v-else class="text-xs text-gray-400">—</span>
                                    </template>

                                    <!-- created_at: solo lectura -->
                                    <template v-else-if="col.key === 'created_at'">
                                        <span class="text-gray-600 dark:text-gray-300 whitespace-nowrap text-sm">{{ fmtDate(issue[col.key]) }}</span>
                                    </template>

                                    <!-- start_date (Fecha Inicio): editable inline; recalcula Días Estimados manteniendo la Fecha Límite. -->
                                    <template v-else-if="col.key === 'start_date'">
                                        <div v-if="editing.key === issue.key && editing.field === 'start_date'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <input
                                                type="date"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @change="$emit('update:editingValue', $event.target.value)"
                                                @blur="$emit('save-edit')"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                data-editing="start_date"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-300 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'start_date', $event)" class="cursor-pointer group/cell">
                                            <span class="text-gray-600 dark:text-gray-300 whitespace-nowrap text-sm">{{ fmtDate(issue.start_date) || '—' }}</span>
                                        </div>
                                    </template>

                                    <!-- fecha_limite: editable inline; si reprogramada muestra original tachada + nueva. -->
                                    <template v-else-if="col.key === 'fecha_limite'">
                                        <div v-if="editing.key === issue.key && editing.field === 'fecha_limite'" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <input
                                                type="date"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @change="$emit('update:editingValue', $event.target.value)"
                                                @blur="$emit('save-edit')"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                data-editing="fecha_limite"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-300 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div v-else-if="issue.fecha_reprogramacion" class="flex flex-col leading-tight whitespace-nowrap cursor-pointer group/cell" @click="$emit('start-edit', issue, 'fecha_limite', $event)">
                                            <span
                                                class="text-xs text-gray-400 dark:text-gray-500 line-through"
                                                :title="`Fecha de vencimiento original: ${fmtDate(issue.fecha_limite)}`"
                                            >{{ fmtDate(issue.fecha_limite) }}</span>
                                            <span
                                                class="text-sm font-medium text-amber-600 dark:text-amber-400"
                                                :title="`Reprogramada a ${fmtDate(issue.fecha_reprogramacion)}`"
                                            >{{ fmtDate(issue.fecha_reprogramacion) }}</span>
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, 'fecha_limite', $event)" class="cursor-pointer group/cell">
                                            <span class="text-gray-600 dark:text-gray-300 whitespace-nowrap text-sm">{{ fmtDate(issue.fecha_limite) || '—' }}</span>
                                        </div>
                                    </template>

                                    <!-- custom fields -->
                                    <template v-else-if="col.key.startsWith('custom_')">
                                        <div v-if="editing.key === issue.key && editing.field === col.key" class="relative">
                                            <div @click="$emit('save-edit')" class="fixed inset-0 z-40"></div>
                                            <!-- Inputs dinámicos según tipo -->
                                            <select
                                                v-if="['dropdown', 'select'].includes(col.type)"
                                                :value="editing.value"
                                                @change="$emit('update:editingValue', $event.target.value); $emit('save-edit')"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-400 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none"
                                            >
                                                <option value="">— Sin valor —</option>
                                                <option v-for="opt in customFields.find(f => f.column_key === col.key)?.options || []" :key="opt" :value="opt">{{ opt }}</option>
                                            </select>
                                            <input
                                                v-else-if="col.type === 'checkbox'"
                                                type="checkbox"
                                                :checked="editing.value === 'true'"
                                                @change="$emit('update:editingValue', $event.target.checked ? 'true' : 'false'); $emit('save-edit')"
                                                class="relative z-50 w-4 h-4 rounded border-gray-300 text-indigo-600 cursor-pointer"
                                            />
                                            <input
                                                v-else-if="['date', 'timestamp', 'datetime-local'].includes(col.type)"
                                                :type="col.type === 'date' ? 'date' : 'datetime-local'"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @change="$emit('update:editingValue', $event.target.value)"
                                                @blur="$emit('save-edit')"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-300 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            />
                                            <textarea
                                                v-else-if="col.type === 'paragraph'"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @keyup.esc="$emit('cancel-edit')"
                                                @keydown.enter.exact.prevent="$emit('save-edit')"
                                                @keydown.ctrl.enter.prevent="$emit('save-edit')"
                                                rows="3"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-300 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none"
                                            />
                                            <div
                                                v-else-if="col.type === 'people'"
                                                class="relative z-50 flex items-center gap-2 cursor-default bg-white dark:bg-gray-800 rounded border border-indigo-300 px-2 py-1 min-w-[120px]"
                                            >
                                                <template v-if="findUserById(editing.value)">
                                                    <img v-if="findUserById(editing.value).avatar_url" :src="findUserById(editing.value).avatar_url" class="w-6 h-6 rounded-full object-cover aspect-square shrink-0 shadow-sm border border-gray-200 dark:border-gray-700" />
                                                    <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0 shadow-sm">{{ initials(findUserById(editing.value).display_name) }}</span>
                                                    <span class="text-xs text-gray-700 dark:text-gray-200 font-medium truncate max-w-[100px]">{{ findUserById(editing.value).display_name }}</span>
                                                </template>
                                                <span v-else class="text-xs text-gray-400 italic">Sin asignar</span>
                                                <svg class="ml-auto w-3 h-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                            <input
                                                v-else
                                                :type="col.type === 'number' ? 'number' : (col.type === 'url' ? 'url' : 'text')"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                class="relative z-50 w-full text-sm border-none ring-0 focus:ring-0 focus:outline-none bg-transparent text-gray-800 dark:text-gray-100"
                                            />
                                        </div>
                                        <div v-else @click="col.type !== 'formula' ? $emit('start-edit', issue, col.key, $event) : null" :class="col.type !== 'formula' ? 'cursor-pointer group/cell min-w-[80px]' : 'min-w-[80px]'">
                                            <template v-if="col.type === 'checkbox'">
                                                <div class="flex items-center justify-center w-6 h-6">
                                                    <div
                                                        :class="[
                                                            'w-4 h-4 rounded border transition-all duration-150 flex items-center justify-center shadow-sm',
                                                            issue.custom_fields?.[col.key]?.value === 'true'
                                                                ? 'border-indigo-500 bg-indigo-500 dark:border-indigo-400 dark:bg-indigo-400 text-white'
                                                                : 'border-gray-300 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-800/50'
                                                        ]"
                                                    >
                                                        <svg
                                                            v-if="issue.custom_fields?.[col.key]?.value === 'true'"
                                                            class="w-2.5 h-2.5 stroke-[3]"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else-if="col.type === 'url'">
                                                <a v-if="issue.custom_fields?.[col.key]?.value" :href="issue.custom_fields[col.key].value" target="_blank" rel="noopener noreferrer" @click.stop class="text-indigo-600 dark:text-indigo-400 text-xs underline truncate max-w-[120px] block hover:text-indigo-800 dark:hover:text-indigo-300">{{ issue.custom_fields[col.key].value }}</a>
                                                <span v-else class="text-gray-400 text-xs">—</span>
                                            </template>
                                            <template v-else-if="col.type === 'labels'">
                                                <div v-if="issue.custom_fields?.[col.key]?.value" class="flex flex-wrap gap-1">
                                                    <span v-for="tag in issue.custom_fields[col.key].value.split(',').map(s => s.trim()).filter(Boolean)" :key="tag" class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">{{ tag }}</span>
                                                </div>
                                                <span v-else class="text-gray-400 text-xs">—</span>
                                            </template>
                                            <template v-else-if="col.type === 'people'">
                                                <div v-if="findUserById(issue.custom_fields?.[col.key]?.value)" class="flex items-center gap-2">
                                                    <GpUserAvatar
                                                        :user="findUserById(issue.custom_fields[col.key].value)"
                                                        :admin="isSpaceAdmin(findUserById(issue.custom_fields[col.key].value))"
                                                    />
                                                    <span class="text-xs text-gray-700 dark:text-gray-200 font-medium truncate max-w-[120px]">
                                                        {{ findUserById(issue.custom_fields[col.key].value).display_name }}
                                                    </span>
                                                </div>
                                                <span v-else class="text-xs text-gray-400 italic">Sin asignar</span>
                                            </template>
                                            <template v-else>
                                                <span class="text-gray-700 dark:text-gray-200 text-sm">{{ issue.custom_fields?.[col.key]?.value || (col.type === 'formula' ? 'ƒx —' : '—') }}</span>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- fecha_entrega / fecha_aprobacion: normalmente automáticas por estado.
                                         El propietario puede corregirlas/vaciarlas inline (gate owner-only en startEdit).
                                         Para vaciar: usar la ✕ nativa del input de fecha o borrar el valor. -->
                                    <template v-else-if="['fecha_entrega', 'fecha_aprobacion'].includes(col.key)">
                                        <div v-if="editing.key === issue.key && editing.field === col.key" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <input
                                                type="date"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @change="$emit('update:editingValue', $event.target.value)"
                                                @blur="$emit('save-edit')"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                :data-editing="`${col.key}-${issue.key}`"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-300 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div v-else @click="$emit('start-edit', issue, col.key, $event)" class="cursor-pointer group/cell min-w-[80px]">
                                            <span class="text-gray-700 dark:text-gray-200 text-sm">{{ fmtDate(issue[col.key]) || '—' }}</span>
                                        </div>
                                    </template>

                                    <!-- generic fallback for system fields like dias_estimados, updated_at -->
                                    <template v-else>
                                        <div v-if="editing.key === issue.key && editing.field === col.key" class="relative">
                                            <div @click="$emit('cancel-edit')" class="fixed inset-0 z-40"></div>
                                            <input
                                                v-if="['date', 'timestamp', 'datetime-local'].includes(col.type)"
                                                :type="col.type === 'date' ? 'date' : 'datetime-local'"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @change="$emit('update:editingValue', $event.target.value)"
                                                @blur="$emit('save-edit')"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                class="relative z-50 w-full text-sm rounded border border-indigo-300 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            />
                                            <input
                                                v-else
                                                :type="col.type === 'number' ? 'number' : 'text'"
                                                :value="editing.value"
                                                @input="$emit('update:editingValue', $event.target.value)"
                                                @keyup.enter="$emit('save-edit')"
                                                @keyup.esc="$emit('cancel-edit')"
                                                class="relative z-50 w-full text-sm border-none ring-0 focus:ring-0 focus:outline-none bg-transparent text-gray-800 dark:text-gray-100"
                                            />
                                        </div>
                                        <div v-else @click="['created_at', 'updated_at'].includes(col.key) ? null : $emit('start-edit', issue, col.key, $event)" :class="['created_at', 'updated_at'].includes(col.key) ? '' : 'cursor-pointer group/cell min-w-[80px]'">
                                            <span class="text-gray-700 dark:text-gray-200 text-sm">{{ fmtDate(issue[col.key]) }}</span>
                                        </div>
                                    </template>
                                </td>

                                <!-- Seguimiento: justo después de TIPO (o al final si TIPO está oculta) -->
                                <td v-if="seguimientoGoesAfter(col, idx)" class="px-3 py-3 whitespace-nowrap w-48">
                                    <div :class="['flex items-center gap-1.5', issue._isChild ? 'ml-8' : 'justify-center']">
                                        <!-- Flecha: desplegar subactividades (con su contador). Aplica a actividades
                                             raíz y a reprogramaciones (-Rn); NO a las subactividades (1 nivel). -->
                                        <button
                                            v-if="!issue._isChild && (issue.sub_actividades_count > 0 || canUserWrite)"
                                            @click.stop="$emit('toggle-expand', issue)"
                                            :title="isExpanded(issue.key) ? 'Ocultar subactividades' : `Ver subactividades (${issue.sub_actividades_count || 0})`"
                                            class="relative inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-indigo-600 bg-gray-100/70 hover:bg-indigo-50 border border-gray-200 transition-colors dark:bg-gray-800/70 dark:text-gray-400 dark:hover:text-indigo-300 dark:border-gray-700"
                                        >
                                            <span v-if="issue.sub_actividades_count > 0" class="absolute -top-1.5 -left-1.5 min-w-[15px] h-[15px] px-1 inline-flex items-center justify-center rounded-full bg-indigo-600 text-white text-[9px] font-bold leading-none ring-2 ring-white dark:ring-gray-900">{{ issue.sub_actividades_count }}</span>
                                            <svg v-if="childrenLoading[issue.key]" class="w-4 h-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <svg v-else class="w-4 h-4 transition-transform duration-150" :class="isExpanded(issue.key) ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                        </button>
                                        <!-- Slot fijo cuando la flecha no aplica (sin permiso/sin subactividades): mantiene alineada la columna entre filas -->
                                        <span v-else-if="!issue._isChild" class="w-7 h-7 shrink-0" aria-hidden="true"></span>
                                        <!-- Tareas (checklist) -->
                                        <button @click.stop="$emit('open-subtask', issue)" :title="`Tareas (${issue.sub_task_count || 0})`" class="relative inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-teal-600 bg-gray-100/70 hover:bg-teal-50 border border-gray-200 transition-colors dark:bg-gray-800/70 dark:text-gray-400 dark:hover:text-teal-300 dark:border-gray-700">
                                            <span v-if="(issue.sub_task_count || 0) > 0" class="absolute -top-1.5 -left-1.5 min-w-[15px] h-[15px] px-1 inline-flex items-center justify-center rounded-full bg-teal-600 text-white text-[9px] font-bold leading-none ring-2 ring-white dark:ring-gray-900">{{ issue.sub_task_count }}</span>
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" /></svg>
                                        </button>
                                        <button @click.stop="$emit('open-timeline', issue.key)" :title="`Seguimiento de cambios (${issue.audit_changes_count || 0})`" class="relative inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-indigo-600 bg-gray-100/70 hover:bg-indigo-100 border border-gray-200 transition-colors dark:bg-gray-800/70 dark:text-gray-400 dark:hover:text-indigo-300 dark:border-gray-700">
                                            <span v-if="(issue.audit_changes_count || 0) > 0" class="absolute -top-1.5 -left-1.5 min-w-[15px] h-[15px] px-1 inline-flex items-center justify-center rounded-full bg-amber-500 text-white text-[9px] font-bold leading-none ring-2 ring-white dark:ring-gray-900">{{ issue.audit_changes_count }}</span>
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                        <button @click.stop="$emit('open-history', issue.key)" :title="`Historial de actividades (${issue.activity_history_count || 0})`" class="relative inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-violet-600 bg-gray-100/70 hover:bg-violet-100 border border-gray-200 transition-colors dark:bg-gray-800/70 dark:text-gray-400 dark:hover:text-violet-300 dark:border-gray-700">
                                            <span v-if="(issue.activity_history_count || 0) > 0" class="absolute -top-1.5 -left-1.5 min-w-[15px] h-[15px] px-1 inline-flex items-center justify-center rounded-full bg-violet-600 text-white text-[9px] font-bold leading-none ring-2 ring-white dark:ring-gray-900">{{ issue.activity_history_count }}</span>
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.598.598 0 01-.474-.065.598.598 0 01-.251-.397 1.39 1.39 0 00-.378-1.277 1.39 1.39 0 00-1.277-.378.598.598 0 01-.397-.251.598.598 0 01-.065-.474l.722-2.723A8.134 8.134 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                                        </button>
                                        <!-- Botón reprogramaciones: actividades raíz y subactividades (no en versiones -Rn) -->
                                        <button
                                            v-if="!issue._isReprog && (issue.reprogramaciones_count > 0 || canApprove)"
                                            @click.stop="$emit('toggle-expand-reprog', issue)"
                                            :title="isReprogExpanded(issue.key) ? 'Ocultar reprogramaciones' : `Historial de reprogramaciones (${issue.reprogramaciones_count || 0})`"
                                            class="relative inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-amber-600 bg-gray-100/70 hover:bg-amber-50 border border-gray-200 transition-colors dark:bg-gray-800/70 dark:text-gray-400 dark:hover:text-amber-300 dark:border-gray-700"
                                        >
                                            <span v-if="(issue.reprogramaciones_count || 0) > 0" class="absolute -top-1.5 -left-1.5 min-w-[15px] h-[15px] px-1 inline-flex items-center justify-center rounded-full bg-amber-500 text-white text-[9px] font-bold leading-none ring-2 ring-white dark:ring-gray-900">{{ issue.reprogramaciones_count }}</span>
                                            <svg v-if="reprogLoading[issue.key]" class="w-4 h-4 animate-spin text-amber-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <svg v-else class="w-4 h-4" :class="isReprogExpanded(issue.key) ? 'text-amber-600 dark:text-amber-400' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </button>
                                        <!-- Slot fijo cuando el botón de reprogramaciones no aplica: mantiene alineada la columna -->
                                        <span v-else-if="!issue._isChild && !issue._isReprog" class="w-7 h-7 shrink-0" aria-hidden="true"></span>
                                        <button v-if="issue._isChild && canManage" @click.stop="$emit('delete-subactividad', issue)" title="Eliminar subactividad" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 hover:text-red-600 bg-gray-100/70 hover:bg-red-50 border border-gray-200 transition-colors dark:bg-gray-800/70 dark:text-gray-400 dark:hover:text-red-300 dark:border-gray-700">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </template>
                        </tr>
                        </template>
                    </template>

                    <tr v-if="!groupedIssues.length && !tableLoading">
                        <td :colspan="2 + visibleColumns.length" class="px-4 py-10 text-center text-sm text-gray-400">
                            No se encontraron issues con los filtros actuales.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <IssueTableFooter
            :filtered-count="filteredCount"
            :total-loaded="allIssuesCount"
            :total-count="totalCount"
            :is-last="isLast"
            :loading="loadMoreLoading"
            @load-more="$emit('load-more')"
        />
    </div>
</template>

<style scoped>
/* Barras de scroll horizontales del tablero (superior sincronizada + inferior de la tabla):
   thumb indigo para que se noten en modo claro. Scoped a este componente — no toca CSS global. */
.custom-scrollbar {
    scrollbar-width: thin;                 /* Firefox */
    scrollbar-color: #067DBE transparent;  /* thumb indigo-500 / track transparente */
}
.custom-scrollbar::-webkit-scrollbar {
    height: 10px;
    width: 10px;
    cursor: pointer;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #067DBE;   /* indigo-500 */
    border-radius: 9999px;
    border: 2px solid transparent;
    background-clip: content-box;
    cursor: pointer;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #056599;   /* indigo-600 */
}
.dark .custom-scrollbar {
    scrollbar-color: #079DED transparent;  /* indigo-400 en modo oscuro */
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #079DED;   /* indigo-400 */
}

/* Avatares de usuario (foto o iniciales) SIEMPRE redondos. Algún estilo global
   de tablas le pisa el `rounded-full`; este override está scoped a este
   componente (no toca CSS global) y gana por especificidad + !important. */
.w-6.h-6.rounded-full {
    border-radius: 9999px !important;
    aspect-ratio: 1 / 1 !important;
}

/* Flash índigo (2 parpadeos lentos) al enfocar un issue desde "Mis pendientes".
   La clase la añade JS sobre el <tr>; :global para que aplique pese al scope.
   indigo-500 en claro, indigo-400 en oscuro para que se vea en ambos modos. */
@keyframes gpFlash {
    0%, 50%, 100% { background-color: rgb(99 102 241 / 0); }
    25%, 75%      { background-color: rgb(99 102 241 / 0.38); }
}
/* DARK — CAUSA REAL (medida en Chrome): app.css global tiene
   `.dark tbody tr { background-color: ... !important }` y un !important de autor le GANA
   a cualquier animación CSS → el flash sobre el <tr> corre pero nunca llega a pintar.
   Las CELDAS no tienen background forzado en dark (`.dark td` solo toca border/color),
   así que en oscuro el flash se anima sobre los <td> de la fila: ahí nada lo bloquea.
   (No se toca app.css: es global y romperlo afecta a otros módulos. Claro queda igual.) */
@keyframes gpFlashDark {
    0%, 50%, 100% { background-color: rgb(129 140 248 / 0); }
    25%, 75%      { background-color: rgb(129 140 248 / 0.8); }
}
:global(tr.gp-flash) { animation: gpFlash 1.8s ease-in-out 1; }
:global(.dark tr.gp-flash) { animation: none; } /* en dark el tr no anima (estaría bloqueado) */
:global(.dark tr.gp-flash > td) { animation: gpFlashDark 1.8s ease-in-out 1; }
</style>
