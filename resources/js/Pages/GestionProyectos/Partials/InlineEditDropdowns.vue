<script setup>
import { computed, ref, watch } from 'vue';
import { severityIcon } from '@/Composables/GestionProyectos/useSeverityIcon';

const props = defineProps({
    editing:              { type: Object,   required: true },
    dropdownAnchor:       { type: Object,   default: null },
    dropdownStyle:        { type: Object,   default: () => ({}) },
    statusOptions:        { type: Array,    default: () => [] },
    criticalStatuses:     { type: Array,    default: () => ['Finalizado', 'Reprogramado'] },
    canApproveOnly:       { type: Boolean,  default: false },
    canApprove:           { type: Boolean,  default: false },
    allIssues:            { type: Array,    default: () => [] },
    // Estado vigente del ítem en edición. Se pasa explícitamente porque las
    // subactividades (-Sn) y versiones reprogramadas (-Rn) NO viven en allIssues,
    // donde el lookup fallaría y no marcaría el estado actual.
    currentStatusName:    { type: String,   default: null },
    jiraPriorities:       { type: Array,    default: () => [] },
    priorityOptions:      { type: Array,    default: () => [] },
    uniquePriorities:     { type: Array,    default: () => [] },
    assigneeSuggestions:  { type: Array,    default: () => [] },
    reporterSuggestions:  { type: Array,    default: () => [] },
    assigneeLoading:      { type: Boolean,  default: false },
    reporterLoading:      { type: Boolean,  default: false },
    teamOptions:          { type: Array,    default: () => [] },
    // Catálogo de categorías del espacio activo [{id, name}] (page prop spaceCategorias).
    // Crece solo: si el usuario escribe una nueva, el backend la da de alta al guardar.
    spaceCategorias:      { type: Array,    default: () => [] },
    initials:             { type: Function, required: true },
});

const emit = defineEmits([
    'cancel',
    'status-select',
    'priority-select',
    'assignee-input-inline',
    'assignee-focus-inline',
    'select-assignee-inline',
    'select-team-inline',
    'reporter-input-inline',
    'reporter-focus-inline',
    'select-reporter-inline',
    // Categoría elegida o escrita (string) / vaciada (null).
    'categoria-select',
]);

// Estados visibles en el dropdown según el rol:
//  - Aprobador puro (canApproveOnly): solo críticos (aprobar/rechazar).
//  - Escritor sin aprobación (Ejecutor): solo NO críticos (Pendiente, En Curso, En Pausa, En Revisión).
//  - Escritor + aprobador (Propietario/Administrador/admin): todos.
const visibleStatuses = computed(() => {
    if (props.canApproveOnly) {
        return props.statusOptions.filter((s) => props.criticalStatuses.includes(s));
    }
    if (!props.canApprove) {
        return props.statusOptions.filter((s) => !props.criticalStatuses.includes(s));
    }
    return props.statusOptions;
});

// Estado vigente para marcar el check en el dropdown: prioriza el prop explícito
// (cubre subactividades/-Rn) y cae al lookup en allIssues por retrocompatibilidad.
const currentStatus = computed(() =>
    props.currentStatusName
    ?? props.allIssues.find((i) => i.key === props.editing.key)?.status?.name
);

// ─── Categoría: combo "elegir del catálogo o escribir una nueva" ────────────
// Texto de búsqueda del combo. Se limpia cada vez que se abre en otra celda.
const categoriaQuery = ref('');

// Categorías creadas al vuelo en esta sesión: el page prop spaceCategorias no se
// refresca hasta recargar, así que las guardamos aquí para que aparezcan de
// inmediato en el desplegable del resto de filas (mismo criterio que las etiquetas
// del modal de creación).
const localAddedCategorias = ref([]);

const categoriaNames = computed(() => {
    const set = new Set([
        ...props.spaceCategorias.map((c) => (typeof c === 'string' ? c : c?.name)).filter(Boolean),
        ...localAddedCategorias.value,
    ]);
    return [...set].sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));
});

const filteredCategorias = computed(() => {
    const q = categoriaQuery.value.trim().toLowerCase();
    if (!q) return categoriaNames.value;
    return categoriaNames.value.filter((n) => n.toLowerCase().includes(q));
});

// Lo escrito es una categoría NUEVA (no existe en el catálogo, ignorando mayúsculas).
const nuevaCategoria = computed(() => {
    const q = categoriaQuery.value.trim();
    if (!q) return null;
    return categoriaNames.value.some((n) => n.toLowerCase() === q.toLowerCase()) ? null : q;
});

// Elegir del catálogo, crear al vuelo (name) o vaciar (null).
function pickCategoria(name) {
    const val = name ? String(name).trim() : null;
    if (val && !categoriaNames.value.includes(val)) localAddedCategorias.value.push(val);
    emit('categoria-select', val);
}

// Enter sobre el buscador: si coincide con una del catálogo se respeta su
// capitalización; si no, se crea con el texto tal cual.
function commitCategoriaInput() {
    const q = categoriaQuery.value.trim();
    if (!q) return;
    const match = categoriaNames.value.find((n) => n.toLowerCase() === q.toLowerCase());
    pickCategoria(match ?? q);
}

watch(
    () => [props.editing.key, props.editing.field],
    () => { categoriaQuery.value = ''; }
);
</script>

<template>
<Teleport to="body">
    <!-- Overlay click-away global -->
    <div
        v-if="
            editing.key &&
            (
                ['status', 'assignee', 'reporter', 'solicitado_por', 'priority', 'validado_por', 'categoria'].includes(editing.field) ||
                editing._fieldType === 'people'
            )
        "
        @click="$emit('cancel')"
        class="fixed inset-0"
        style="z-index: 9998"
    ></div>

    <!-- Status Dropdown -->
    <div
        v-if="editing.field === 'status' && dropdownAnchor"
        :style="dropdownStyle"
        class="w-48 bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <div
            class="px-3 py-1.5 mb-1 border-b border-gray-50 dark:border-gray-700/50 bg-indigo-600 dark:bg-gray-900/50"
        >
            <span
                class="text-[10px] font-bold text-white uppercase tracking-widest"
                >Cambiar estado</span
            >
        </div>
        <button
            v-for="s in visibleStatuses"
            :key="s"
            @click="
                $emit('status-select', s);
            "
            class="w-full flex items-center justify-between px-4 py-2 text-[11px] font-bold uppercase tracking-wider transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/40"
            :class="
                s === currentStatus
                    ? 'text-indigo-600 dark:text-indigo-600 bg-indigo-50/30 text-[12px]'
                    : 'text-gray-600 dark:text-gray-300'
            "
        >
            {{ s }}
            <span
                v-if="s === currentStatus"
                class="flex items-center justify-center w-5 h-5 bg-indigo-600/85 rounded-full"
            >
                <svg
                    class="w-3 h-3 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="4"
                        d="M5 13l4 4L19 7"
                    />
                </svg>
            </span>
        </button>
    </div>

    <!-- Assignee Dropdown with Search -->
    <div
        v-if="editing.field === 'assignee' && dropdownAnchor"
        :style="dropdownStyle"
        class="w-74 bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <div
            class="px-3 h-14 flex items-center border-b border-indigo-600 dark:border-gray-700/50 bg-indigo-600 dark:bg-gray-900/50"
        >
            <div
                class="flex items-center gap-2 w-full px-2 py-2 bg-gray-50 dark:bg-gray-900/50 rounded-lg"
            >
                <svg
                    class="w-5 h-5 text-indigo-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    type="text"
                    @input="$emit('assignee-input-inline', $event)"
                    @focus="$emit('assignee-focus-inline')"
                    placeholder="Buscar usuario..."
                    data-editing-teleport="assignee"
                    class="flex-1 text-[13px] border-none outline-none focus:outline-none ring-0 focus:ring-0 bg-transparent text-gray-700 dark:text-gray-200 p-0"
                />
            </div>
        </div>
        <div class="max-h-60 overflow-y-auto custom-scrollbar">
            <!-- Quitar asignación (limpia usuario y equipo) -->
            <div
                @mousedown.prevent="$emit('select-assignee-inline', null)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer text-red-600 dark:text-red-400 font-medium text-xs transition-colors border-b border-gray-50 dark:border-gray-700/50"
            >
                <div class="w-7 h-7 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </div>
                <span>Sin asignar</span>
            </div>

            <!-- Sección Equipos -->
            <template v-if="teamOptions.length">
                <div class="px-3 pt-2 pb-1 text-[9px] font-bold uppercase tracking-widest text-violet-500 dark:text-violet-400">Equipos</div>
                <div
                    v-for="t in teamOptions"
                    :key="'team-' + t.id"
                    @mousedown.prevent="$emit('select-team-inline', t)"
                    class="flex items-center gap-3 px-3 py-2 hover:bg-violet-50 dark:hover:bg-violet-500/10 cursor-pointer transition-colors"
                >
                    <span class="w-7 h-7 rounded-lg bg-violet-500 text-white flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </span>
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ t.name }}</span>
                </div>
                <div class="px-3 pt-2 pb-1 text-[9px] font-bold uppercase tracking-widest text-indigo-400 dark:text-indigo-400 border-t border-gray-50 dark:border-gray-700/50">Usuarios</div>
            </template>

            <div
                v-for="u in assigneeSuggestions"
                :key="u.account_id"
                @mousedown.prevent="$emit('select-assignee-inline', u)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors"
            >
                <img
                    v-if="u.avatar_url"
                    :src="u.avatar_url"
                    class="w-7 h-7 rounded-full"
                />
                <span
                    v-else
                    class="w-7 h-7 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center"
                    >{{ initials(u.display_name) }}</span
                >
                <div class="flex flex-col min-w-0">
                    <span
                        class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate"
                        >{{ u.display_name }}</span
                    >
                    <span
                        v-if="u.email"
                        class="text-[9px] text-gray-400 truncate"
                        >{{ u.email }}</span
                    >
                </div>
            </div>
            <div
                v-if="!assigneeSuggestions.length && !assigneeLoading"
                class="px-3 py-4 text-center text-xs text-gray-400 italic"
            >
                No se encontraron usuarios
            </div>
        </div>
    </div>

    <!-- Validado Por Dropdown (idéntico a Persona Asignada: miembros del espacio, sin equipos) -->
    <div
        v-if="editing.field === 'validado_por' && dropdownAnchor"
        :style="dropdownStyle"
        class="w-74 bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <div class="px-3 h-14 flex items-center border-b border-indigo-600 dark:border-gray-700/50 bg-indigo-600 dark:bg-gray-900/50">
            <div class="flex items-center gap-2 w-full px-2 py-2 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                <input
                    type="text"
                    @input="$emit('assignee-input-inline', $event)"
                    @focus="$emit('assignee-focus-inline')"
                    placeholder="Buscar usuario..."
                    data-editing-teleport="validado_por"
                    class="flex-1 text-[13px] border-none outline-none focus:outline-none ring-0 focus:ring-0 bg-transparent text-gray-700 dark:text-gray-200 p-0"
                />
            </div>
        </div>
        <div class="max-h-60 overflow-y-auto custom-scrollbar">
            <!-- Sin validar -->
            <div
                @mousedown.prevent="$emit('select-assignee-inline', null)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer text-red-600 dark:text-red-400 font-medium text-xs transition-colors border-b border-gray-50 dark:border-gray-700/50"
            >
                <div class="w-7 h-7 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </div>
                <span>Sin validar</span>
            </div>

            <div
                v-for="u in assigneeSuggestions"
                :key="u.account_id"
                @mousedown.prevent="$emit('select-assignee-inline', u)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors"
            >
                <img v-if="u.avatar_url" :src="u.avatar_url" class="w-7 h-7 rounded-full" />
                <span v-else class="w-7 h-7 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center">{{ initials(u.display_name) }}</span>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ u.display_name }}</span>
                    <span v-if="u.email" class="text-[9px] text-gray-400 truncate">{{ u.email }}</span>
                </div>
            </div>
            <div v-if="!assigneeSuggestions.length && !assigneeLoading" class="px-3 py-4 text-center text-xs text-gray-400 italic">
                No se encontraron usuarios
            </div>
        </div>
    </div>

    <!-- Reporter / Solicitado Por Dropdown with Search -->
    <div
        v-if="(editing.field === 'reporter' || editing.field === 'solicitado_por') && dropdownAnchor"
        :style="dropdownStyle"
        class="w-74 bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <!-- Cabecera Índigo Clonada -->
        <div
            class="px-3 h-14 flex items-center border-b border-indigo-600 dark:border-gray-700/50 bg-indigo-600 dark:bg-gray-900/50"
        >
            <div
                class="flex items-center gap-2 w-full px-2 py-2 bg-gray-50 dark:bg-gray-900/50 rounded-lg"
            >
                <svg
                    class="w-5 h-5 text-indigo-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    type="text"
                    @input="$emit('reporter-input-inline', $event)"
                    @focus="$emit('reporter-focus-inline')"
                    placeholder="Buscar solicitante..."
                    :data-editing-teleport="editing.field"
                    class="flex-1 text-[13px] border-none outline-none focus:outline-none ring-0 focus:ring-0 bg-transparent text-gray-700 dark:text-gray-200 p-0"
                />
            </div>
        </div>

        <!-- Lista de Sugerencias -->
        <div class="max-h-60 overflow-y-auto custom-scrollbar">
            <div
                v-for="u in reporterSuggestions"
                :key="u.account_id"
                @mousedown.prevent="$emit('select-reporter-inline', u)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors"
            >
                <img
                    v-if="u.avatar_url"
                    :src="u.avatar_url"
                    class="w-7 h-7 rounded-full"
                />
                <span
                    v-else
                    class="w-7 h-7 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center"
                >
                    {{ initials(u.display_name) }}
                </span>

                <div class="flex flex-col min-w-0">
                    <span
                        class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate"
                        >{{ u.display_name }}</span
                    >
                    <span
                        v-if="u.email"
                        class="text-[9px] text-gray-400 truncate"
                        >{{ u.email }}</span
                    >
                </div>
            </div>

            <div
                v-if="!reporterSuggestions.length && !reporterLoading"
                class="px-3 py-4 text-center text-xs text-gray-400 italic"
            >
                No se encontraron usuarios
            </div>
        </div>
    </div>

    <!-- Categoría: catálogo por espacio (crece solo) + texto libre.
         Se puede elegir una existente, escribir una nueva o vaciar el campo. -->
    <div
        v-if="editing.field === 'categoria' && dropdownAnchor"
        :style="dropdownStyle"
        class="w-64 bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <div class="px-3 h-14 flex items-center border-b border-indigo-600 dark:border-gray-700/50 bg-indigo-600 dark:bg-gray-900/50">
            <div class="flex items-center gap-2 w-full px-2 py-2 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="categoriaQuery"
                    type="text"
                    maxlength="100"
                    placeholder="Buscar o escribir categoría..."
                    data-editing-teleport="categoria"
                    autocomplete="off"
                    @keyup.enter.prevent="commitCategoriaInput"
                    @keyup.esc="$emit('cancel')"
                    class="flex-1 text-[13px] border-none outline-none focus:outline-none ring-0 focus:ring-0 bg-transparent text-gray-700 dark:text-gray-200 p-0"
                />
            </div>
        </div>
        <div class="max-h-60 overflow-y-auto custom-scrollbar">
            <!-- Crear al vuelo: se guarda en el catálogo del espacio al confirmar -->
            <div
                v-if="nuevaCategoria"
                @mousedown.prevent="pickCategoria(nuevaCategoria)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer text-indigo-600 dark:text-indigo-400 font-medium text-xs transition-colors border-b border-gray-50 dark:border-gray-700/50"
            >
                <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </div>
                <span class="truncate">Crear «{{ nuevaCategoria }}»</span>
            </div>

            <!-- Vaciar la categoría (siempre es opcional) -->
            <div
                @mousedown.prevent="pickCategoria(null)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer text-red-600 dark:text-red-400 font-medium text-xs transition-colors border-b border-gray-50 dark:border-gray-700/50"
            >
                <div class="w-7 h-7 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </div>
                <span>Sin categoría</span>
            </div>

            <div
                v-for="c in filteredCategorias"
                :key="c"
                @mousedown.prevent="pickCategoria(c)"
                class="flex items-center justify-between gap-2 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors"
            >
                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-sm border border-indigo-500 bg-indigo-500 text-white dark:border-indigo-600 dark:bg-indigo-600 dark:text-white truncate">{{ c }}</span>
                <span
                    v-if="c === editing.value"
                    class="flex items-center justify-center w-4 h-4 bg-indigo-600 rounded-full shrink-0"
                >
                    <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            </div>

            <div
                v-if="!filteredCategorias.length && !nuevaCategoria"
                class="px-3 py-4 text-center text-xs text-gray-400 italic"
            >
                Aún no hay categorías. Escribe una para crearla.
            </div>
        </div>
    </div>

    <!-- Priority Dropdown -->
    <div
        v-if="editing.field === 'priority' && dropdownAnchor"
        :style="dropdownStyle"
        class="w-48 bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 rounded-xl py-1 overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <!-- Caso: jiraPriorities -->
        <div
            v-for="p in jiraPriorities"
            :key="p.id"
            @mousedown.prevent="
                $emit('priority-select', p.name);
            "
            class="flex items-center justify-between px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors group"
        >
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 shrink-0" :class="severityIcon(p.name).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(p.name).path" /></svg>
                <span
                    class="text-xs text-gray-700 dark:text-gray-200"
                    >{{ p.name }}</span
                >
            </div>

            <!-- Check redondo índigo -->
            <span
                v-if="editing.value === p.name"
                class="flex items-center justify-center w-4 h-4 bg-indigo-600 rounded-full"
            >
                <svg
                    class="w-2.5 h-2.5 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="4"
                        d="M5 13l4 4L19 7"
                    />
                </svg>
            </span>
        </div>

        <!-- Caso: priorityOptions (Fallback) -->
        <template v-if="!jiraPriorities.length">
            <div
                v-for="p in priorityOptions"
                :key="p"
                @mousedown.prevent="
                    $emit('priority-select', p);
                "
                class="flex items-center justify-between px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors group"
            >
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 shrink-0" :class="severityIcon(p).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(p).path" /></svg>
                    <span
                        class="text-xs text-gray-700 dark:text-gray-200"
                        >{{ p }}</span
                    >
                </div>

                <!-- Check redondo índigo -->
                <span
                    v-if="editing.value === p"
                    class="flex items-center justify-center w-4 h-4 bg-indigo-600/80 rounded-full"
                >
                    <svg
                        class="w-2.5 h-2.5 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="4"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </span>
            </div>
        </template>
    </div>

    <!-- People custom field dropdown -->
    <div
        v-if="editing._fieldType === 'people' && dropdownAnchor"
        :style="dropdownStyle"
        class="w-74 bg-white dark:bg-gray-800 shadow-2xl border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        <div
            class="px-3 h-14 flex items-center border-b border-indigo-600 dark:border-gray-700/50 bg-indigo-600 dark:bg-gray-900/50"
        >
            <div
                class="flex items-center gap-2 w-full px-2 py-2 bg-gray-50 dark:bg-gray-900/50 rounded-lg"
            >
                <svg
                    class="w-5 h-5 text-indigo-600 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    type="text"
                    @input="$emit('assignee-input-inline', $event)"
                    @focus="$emit('assignee-focus-inline')"
                    placeholder="Buscar persona..."
                    data-editing-teleport="people"
                    class="flex-1 text-[13px] border-none outline-none focus:outline-none ring-0 focus:ring-0 bg-transparent text-gray-700 dark:text-gray-200 p-0"
                />
            </div>
        </div>
        <div class="max-h-60 overflow-y-auto custom-scrollbar">
            <!-- Clear assignment -->
            <div
                @mousedown.prevent="$emit('select-assignee-inline', null)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer text-red-600 dark:text-red-400 font-medium text-xs transition-colors border-b border-gray-50 dark:border-gray-700/50"
            >
                <div class="w-7 h-7 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <span>Quitar persona</span>
            </div>

            <!-- List suggestion users -->
            <div
                v-for="u in assigneeSuggestions"
                :key="u.account_id"
                @mousedown.prevent="$emit('select-assignee-inline', u)"
                class="flex items-center gap-3 px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer transition-colors"
            >
                <img
                    v-if="u.avatar_url"
                    :src="u.avatar_url"
                    class="w-7 h-7 rounded-full shrink-0"
                />
                <span
                    v-else
                    class="w-7 h-7 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0"
                    >{{ initials(u.display_name) }}</span
                >
                <div class="flex flex-col min-w-0">
                    <span
                        class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate"
                        >{{ u.display_name }}</span
                    >
                    <span
                        v-if="u.email"
                        class="text-[9px] text-gray-400 truncate"
                        >{{ u.email }}</span
                    >
                </div>
            </div>
            <div
                v-if="!assigneeSuggestions.length && !assigneeLoading"
                class="px-3 py-4 text-center text-xs text-gray-400 italic"
            >
                No se encontraron usuarios
            </div>
        </div>
    </div>
</Teleport>

</template>
