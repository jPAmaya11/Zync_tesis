<script setup>
import { ref, computed, watch } from 'vue';
import { getLabelColor } from '@/Composables/GestionProyectos/useLabelColors';
import { severityIcon } from '@/Composables/GestionProyectos/useSeverityIcon';
import { issueTypeLabel } from '@/Composables/GestionProyectos/useIssueTypeLabel';

const props = defineProps({
    show:                  { type: Boolean,  default: false },
    createForm:            { type: Object,   required: true },
    projects:              { type: Array,    default: () => [] },
    createIssueTypes:      { type: Array,    default: () => [] },
    createTypesLoading:    { type: Boolean,  default: false },
    jiraPriorities:        { type: Array,    default: () => [] },
    uniquePriorities:      { type: Array,    default: () => [] },
    createStatuses:        { type: Array,    default: () => [] },
    createStatusesLoading: { type: Boolean,  default: false },
    createLabels:          { type: Array,    default: () => [] },
    createLabelsLoading:   { type: Boolean,  default: false },
    // Catálogo de categorías del espacio [{id, name}]. Crece solo: si se escribe una
    // nueva, el backend la da de alta al crear la actividad (ProyectoService::ensureCategoria).
    spaceCategorias:       { type: Array,    default: () => [] },
    assigneeSuggestions:   { type: Array,    default: () => [] },
    assigneeLoading:       { type: Boolean,  default: false },
    createAssigneesLoading:{ type: Boolean,  default: false },
    reporterSuggestions:   { type: Array,    default: () => [] },
    reporterLoading:       { type: Boolean,  default: false },
    createError:           { type: String,   default: null },
    previewReady:          { type: Boolean,  default: false },
    spaceTeams:            { type: Array,    default: () => [] },
    priorityOptions:       { type: Array,    default: () => [] },
    initials:              { type: Function, required: true },
    isIconUrl:             { type: Function, required: true },
    // Si está seteado, el modal crea una subactividad dentro de esta actividad.
    parentKey:             { type: String,   default: null },
    // Fecha de inicio del padre: la start_date de la subactividad no puede ser anterior a esta.
    parentStartDate:       { type: String,   default: null },
    // Switch por-espacio "Bloquear fechas anteriores": true ⇒ se aplica el piso (hoy / inicio del
    // padre) en la Fecha de Inicio; false ⇒ la fecha de inicio queda libre (cualquier fecha).
    bloquearFechas:        { type: Boolean,  default: true },
    // Estado de envío (cubre subactividad vía axios, que no toca createForm.processing).
    submitting:            { type: Boolean,  default: false },
    // Niveles de impacto válidos (desde config; misma lista en actividad y subactividad).
    impactoOptions:        { type: Array,    default: () => ['Crítico', 'Alto', 'Medio', 'Bajo', 'Sin impacto'] },
});

// Hoy en formato YYYY-MM-DD (zona local del navegador). Se usa como mínimo del calendario:
// no se permiten fechas anteriores a hoy al crear. En subactividad, el mínimo del Inicio es
// el inicio del padre (que ya es >= hoy en el flujo normal).
const todayYmd = computed(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const emit = defineEmits([
    'close',
    'submit',
    'assignee-input',
    'assignee-focus',
    'reporter-input',
    'reporter-focus',
    'select-assignee',
    'select-reporter',
]);

// ── Local UI state (purely internal to this modal) ──────────────────────────
const activeTab             = ref('datos');
const showPreviewLocal      = ref(false);
const showProjectDropdown   = ref(false);
const showTypeDropdown      = ref(false);
const showPriorityDropdown  = ref(false);
const showStatusDropdown    = ref(false);
const showAssigneeSugg      = ref(false);
const showReporterSugg      = ref(false);
const showCategoriaSugg     = ref(false);

// Reset all local state when the modal closes
watch(() => props.show, (val) => {
    if (!val) {
        showPreviewLocal.value     = false;
        showProjectDropdown.value  = false;
        showTypeDropdown.value     = false;
        showPriorityDropdown.value = false;
        showStatusDropdown.value   = false;
        showAssigneeSugg.value     = false;
        showReporterSugg.value     = false;
        showCategoriaSugg.value    = false;
        localAddedLabels.value     = [];
    }
});

// ── Computed helpers ─────────────────────────────────────────────────────────

// Estados que NO tienen sentido al CREAR una actividad/subactividad:
// terminales (Finalizado/Cancelado), Reprogramado (tiene su propio flujo de modal)
// y En Pausa (solo se llega tras estar En Curso). Se filtran del selector.
const HIDDEN_CREATE_STATUSES = ['finalizado', 'cancelado', 'reprogramado', 'en pausa'];
const selectableStatuses = computed(() =>
    (props.createStatuses || []).filter(
        (s) => !HIDDEN_CREATE_STATUSES.includes(String(s).trim().toLowerCase())
    )
);

const selectedTypeIcon = computed(() => {
    const name = props.createForm.issue_type;
    if (!name) return null;
    const fromApi = props.createIssueTypes.find((t) => t.name === name);
    return fromApi?.iconUrl || null;
});

// Oculta "Subtarea" del selector de Tipo (solo front): las subtareas se crean
// desde su propio modal, no desde "Crear Tarea".
const selectableIssueTypes = computed(() =>
    props.createIssueTypes.filter((t) => !/sub[\s-]?tarea|sub[\s-]?task/i.test(t.name || ''))
);

const selectedAssigneeObj = computed(() => {
    const id = props.createForm.assignee_account_id;
    if (!id) return null;
    return props.assigneeSuggestions.find((u) => u.account_id === id) || null;
});

const selectedReporterObj = computed(() => {
    // Construido desde el form (persistente), no desde reporterSuggestions (transitorio
    // y vacío tras seleccionar / con el valor por defecto), para que el avatar sí aparezca.
    const id = props.createForm.reporter_account_id;
    if (!id) return null;
    return {
        account_id: id,
        display_name: props.createForm._reporter_display,
        avatar_url: props.createForm._reporter_avatar || null,
    };
});

// ── Local helpers ────────────────────────────────────────────────────────────
function toggleLabel(label) {
    const idx = props.createForm.labels.indexOf(label);
    if (idx === -1) props.createForm.labels.push(label);
    else props.createForm.labels.splice(idx, 1);
}

// ── Categoría: elegir una del espacio o escribir una nueva ──────────────────
// Un solo valor por actividad (a diferencia de las etiquetas) y siempre opcional.
// Se ordena en el cliente con localeCompare en español (insensible a mayúsculas y
// acentos) y NO se confía solo en el ORDER BY del backend: así el orden es idéntico
// al del desplegable inline de la tabla, sin depender de la colación de MySQL.
const categoriaNames = computed(() =>
    (props.spaceCategorias || [])
        .map((c) => (typeof c === 'string' ? c : c?.name))
        .filter(Boolean)
        .sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }))
);

// El desplegable filtra por lo que se va escribiendo; sin texto muestra el catálogo entero.
const filteredCategorias = computed(() => {
    const q = (props.createForm.categoria || '').trim().toLowerCase();
    if (!q) return categoriaNames.value;
    return categoriaNames.value.filter((n) => n.toLowerCase().includes(q));
});

function pickCategoria(name) {
    props.createForm.categoria = name;
    showCategoriaSugg.value = false;
}

// Etiquetas añadidas al vuelo en esta sesión del modal (no están aún en createLabels del espacio).
const localAddedLabels = ref([]);

// Etiquetas disponibles = espacio + añadidas al vuelo localmente.
// NO incluye createForm.labels para que la selección/deselección no afecte la visibilidad.
const availableLabels = computed(() => {
    const set = new Set([
        ...(props.createLabels || []),
        ...localAddedLabels.value,
    ]);
    return [...set];
});

// Crear etiqueta al vuelo: se añade al catálogo local Y se marca como seleccionada.
// El backend la persiste en el espacio vía ensureLabels() al guardar el issue.
const newLabelName = ref('');
function addNewLabel() {
    const name = newLabelName.value.trim();
    if (!name) return;
    // Añadir al catálogo local si no existe ya (en espacio ni en locales)
    if (!availableLabels.value.includes(name)) {
        localAddedLabels.value.push(name);
    }
    // Seleccionarla automáticamente
    if (!props.createForm.labels.includes(name)) {
        props.createForm.labels.push(name);
    }
    newLabelName.value = '';
}
</script>

<template>
<Teleport to="body">
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4 pb-8"
        @keydown.escape.stop="$emit('close')"
    >
        <div
            class="absolute inset-0 bg-black/65 backdrop-blur-sm"
            @click="$emit('close')"
        />

        <div
            class="relative z-10 w-full max-w-4xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md shadow-2xl overflow-hidden flex flex-col max-h-[80vh]"
        >
            <!-- Header with gradient pattern from ModalForm -->
            <div
                class="relative bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-600 dark:from-blue-500 dark:via-indigo-500 dark:to-cyan-500 px-8 py-6 shrink-0"
            >
                <!-- Decorative pattern overlay -->
                <div
                    class="absolute inset-0 bg-grid-white/10 [mask-image:radial-gradient(white,transparent_85%)]"
                ></div>

                <div class="relative flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- Icon with animated border -->
                        <div class="relative">
                            <div
                                class="absolute inset-0 bg-white/20 rounded-xl blur-md"
                            ></div>
                            <div
                                class="relative bg-white/10 backdrop-blur-sm p-3 rounded-xl border border-white/20"
                            >
                                <svg
                                    class="w-7 h-7 text-white drop-shadow-lg"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 4.5v15m7.5-7.5h-15"
                                    />
                                </svg>
                            </div>
                        </div>

                        <!-- Title -->
                        <div>
                            <h2
                                class="text-2xl font-bold text-white drop-shadow-md"
                            >
                                {{ parentKey ? 'Nueva subactividad' : 'Crear actividad' }}
                            </h2>
                            <p class="text-blue-100/90 text-sm mt-1">
                                {{ parentKey ? `Dentro de ${parentKey}` : 'Complete la información requerida' }}
                            </p>
                        </div>
                    </div>

                    <!-- Right side buttons -->
                    <div class="flex items-center gap-2">
                        <!-- Toggle previsualización -->
                        <button
                            v-if="previewReady"
                            @click="showPreviewLocal = !showPreviewLocal"
                            type="button"
                            :class="[
                                'group relative px-3.5 py-2 rounded-xl font-semibold transition-all duration-200',
                                showPreviewLocal
                                    ? 'bg-white text-indigo-600 shadow-lg hover:shadow-xl'
                                    : 'bg-white/10 text-white border border-white/20 hover:bg-white/20 backdrop-blur-sm',
                            ]"
                        >
                            {{
                                showPreviewLocal ? 'Editar' : 'Vista previa'
                            }}
                        </button>
                        <!-- Close button -->
                        <button
                            @click="$emit('close')"
                            class="relative group bg-white/10 hover:bg-white/20 backdrop-blur-sm p-2.5 rounded-xl border border-white/20 transition-all duration-200"
                        >
                            <svg
                                class="w-5 h-5 text-white transition-transform group-hover:rotate-90 duration-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                stroke-width="2.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── CUERPO (CON SCROLL) ── -->
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                <!-- ── Barra de secciones (tabs de subrayado) ── -->
                <div class="flex items-end px-6 pt-3 border-b border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="activeTab = 'datos'"
                        :class="['px-4 py-2.5 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'datos' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >Nombres / Datos</button>
                    <button
                        type="button"
                        @click="activeTab = 'alcance'"
                        :class="['px-4 py-2.5 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'alcance' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >Alcance</button>
                    <button
                        type="button"
                        @click="activeTab = 'fechas'"
                        :class="['px-4 py-2.5 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'fechas' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >Fechas</button>
                </div>

                <!-- Error de Jira -->
                <div
                    v-if="createError"
                    class="mx-6 mt-4 p-3 rounded-lg border border-red-200 bg-red-50 text-red-700 text-xs"
                >
                    <strong>Error:</strong> {{ createError }}
                </div>

                <!-- ── VISTA PREVIA ── -->
                <div
                    v-if="showPreviewLocal && previewReady"
                    class="p-6 space-y-4"
                >
                    <div
                        class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-5 space-y-4"
                    >
                        <div class="flex items-center gap-2 flex-wrap">
                            <span
                                class="px-2 py-0.5 text-[10px] font-bold rounded bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300"
                                >PREVIEW</span
                            >
                            <span
                                class="px-2 py-0.5 text-[11px] font-semibold rounded border border-blue-200 bg-blue-50 text-blue-700"
                                >{{ issueTypeLabel(createForm.issue_type) }}</span
                            >
                            <span
                                v-if="createForm.priority"
                                class="px-2 py-0.5 text-[11px] font-semibold rounded border border-amber-200 bg-amber-50 text-amber-700"
                                >{{ createForm.priority }}</span
                            >
                        </div>
                        <h4
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ createForm.summary }}
                        </h4>
                        <p
                            v-if="createForm.description"
                            class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap"
                        >
                            {{ createForm.description }}
                        </p>
                        <p v-else class="text-sm text-gray-400 italic">
                            Sin descripción.
                        </p>
                        <div
                            class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-200 dark:border-gray-600"
                        >
                            <span
                                >Proyecto:
                                <strong>{{
                                    createForm.project_key
                                }}</strong></span
                            >
                            <span v-if="createForm._assignee_display"
                                >Asignado:
                                <strong>{{
                                    createForm._assignee_display
                                }}</strong></span
                            >
                        </div>
                    </div>
                </div>

                <!-- ── FORMULARIO ── -->
                <form
                    v-else
                    @submit.prevent="$emit('submit')"
                    id="jira-create-form"
                    class="p-6 space-y-3"
                >
                    <!-- ══════ PANEL: Nombres / Datos ══════ -->
                    <div v-show="activeTab === 'datos'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                >Espacio
                                <span class="text-red-500"
                                    >*</span
                                ></label
                            >

                            <!-- Dropdown Trigger -->
                            <button
                                type="button"
                                @click="
                                    showProjectDropdown =
                                        !showProjectDropdown
                                "
                                class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            >
                                <div class="flex items-center gap-2">
                                    <template
                                        v-if="
                                            projects.find(
                                                (p) =>
                                                    p.key ===
                                                    createForm.project_key
                                            )
                                        "
                                    >
                                        <img
                                            v-if="
                                                projects.find(
                                                    (p) =>
                                                        p.key ===
                                                        createForm.project_key
                                                ).icon &&
                                                isIconUrl(
                                                    projects.find(
                                                        (p) =>
                                                            p.key ===
                                                            createForm.project_key
                                                    ).icon
                                                )
                                            "
                                            :src="
                                                projects.find(
                                                    (p) =>
                                                        p.key ===
                                                        createForm.project_key
                                                ).icon
                                            "
                                            class="w-5 h-5 rounded-sm object-cover shrink-0"
                                        />
                                        <span
                                            v-else-if="
                                                projects.find(
                                                    (p) =>
                                                        p.key ===
                                                        createForm.project_key
                                                ).icon
                                            "
                                            class="w-5 h-5 rounded-sm bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-xs shrink-0"
                                            >{{
                                                projects.find(
                                                    (p) =>
                                                        p.key ===
                                                        createForm.project_key
                                                ).icon
                                            }}</span
                                        >
                                        <span class="font-medium">{{
                                            projects.find(
                                                (p) =>
                                                    p.key ===
                                                    createForm.project_key
                                            ).name ||
                                            createForm.project_key
                                        }}</span>
                                    </template>
                                    <span v-else class="text-gray-400"
                                        >Seleccionar espacio</span
                                    >
                                </div>
                                <svg
                                    class="w-4 h-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>

                            <!-- Click-away Overlay -->
                            <div
                                v-if="showProjectDropdown"
                                class="fixed inset-0 z-40"
                                @click="showProjectDropdown = false"
                            ></div>

                            <!-- Dropdown Menu -->
                            <div
                                v-if="showProjectDropdown"
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg py-1 max-h-60 overflow-y-auto"
                            >
                                <button
                                    v-for="p in projects"
                                    :key="p.key"
                                    type="button"
                                    @click="
                                        createForm.project_key = p.key;
                                        showProjectDropdown = false;
                                    "
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-gray-700 dark:text-gray-200 text-left transition-colors"
                                >
                                    <img
                                        v-if="
                                            p.icon && isIconUrl(p.icon)
                                        "
                                        :src="p.icon"
                                        class="w-5 h-5 rounded-sm object-cover shrink-0"
                                    />
                                    <span
                                        v-else-if="p.icon"
                                        class="w-5 h-5 rounded-sm bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-xs shrink-0"
                                        >{{ p.icon }}</span
                                    >
                                    <span class="font-medium">{{
                                        p.name || p.key
                                    }}</span>
                                    <span
                                        class="ml-auto text-[10px] text-gray-400 font-mono"
                                        >{{ p.key }}</span
                                    >
                                </button>
                            </div>
                            <p
                                v-if="createForm.errors.project_key"
                                class="mt-1 text-xs text-red-500"
                            >
                                {{ createForm.errors.project_key }}
                            </p>
                        </div>
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                            >
                                Tipo Actividad
                                <span class="text-red-500">*</span>
                                <svg
                                    v-if="createTypesLoading"
                                    class="inline animate-spin w-3 h-3 ml-1 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                            </label>

                            <!-- Dropdown Trigger -->
                            <button
                                type="button"
                                @click="
                                    showTypeDropdown = !showTypeDropdown
                                "
                                class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            >
                                <span
                                    v-if="!createForm.issue_type"
                                    class="text-gray-400"
                                    >{{
                                        createTypesLoading
                                            ? 'Cargando...'
                                            : 'Seleccionar tipo'
                                    }}</span
                                >
                                <div
                                    v-else
                                    class="flex items-center gap-2"
                                >
                                    <img
                                        v-if="selectedTypeIcon"
                                        :src="selectedTypeIcon"
                                        class="w-4 h-4 rounded-sm"
                                    />
                                    <span class="font-medium">{{
                                        issueTypeLabel(createForm.issue_type)
                                    }}</span>
                                </div>
                                <svg
                                    class="w-4 h-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>

                            <!-- Click-away Overlay -->
                            <div
                                v-if="showTypeDropdown"
                                class="fixed inset-0 z-40"
                                @click="showTypeDropdown = false"
                            ></div>

                            <!-- Dropdown Menu -->
                            <div
                                v-if="showTypeDropdown"
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg py-1 max-h-48 overflow-y-auto"
                            >
                                <template
                                    v-if="selectableIssueTypes.length"
                                >
                                    <button
                                        v-for="t in selectableIssueTypes"
                                        :key="t.id"
                                        type="button"
                                        @click="
                                            createForm.issue_type =
                                                t.name;
                                            createForm.issue_type_id =
                                                t.id ?? '';
                                            showTypeDropdown = false;
                                        "
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-gray-700 dark:text-gray-200 text-left transition-colors"
                                    >
                                        <img
                                            v-if="t.iconUrl"
                                            :src="t.iconUrl"
                                            class="w-4 h-4 rounded-sm shrink-0"
                                        />
                                        <span class="font-medium">{{
                                            issueTypeLabel(t.name)
                                        }}</span>
                                    </button>
                                </template>
                                <template v-else>
                                    <div
                                        class="px-3 py-4 text-center text-xs text-gray-400"
                                    >
                                        {{
                                            createTypesLoading
                                                ? 'Cargando tipos...'
                                                : 'No se encontraron tipos'
                                        }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                            >Actividad
                            <span class="text-red-500">*</span></label
                        >
                        <input
                            id="cf-summary"
                            v-model="createForm.summary"
                            type="text"
                            maxlength="255"
                            placeholder="Título breve del issue..."
                            :class="[
                                'w-full px-3 py-2 text-sm rounded-sm border bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40',
                                createForm.errors.summary
                                    ? 'border-red-400'
                                    : 'border-gray-200 dark:border-gray-700',
                            ]"
                            required
                        />
                        <p
                            v-if="createForm.errors.summary"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ createForm.errors.summary }}
                        </p>
                    </div>

                        <!-- Estado Inicial -->
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                >Estado</label
                            >
                            <button
                                type="button"
                                @click="
                                    showStatusDropdown =
                                        !showStatusDropdown
                                "
                                class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            >
                                <span
                                    v-if="!createForm.status"
                                    class="text-gray-400"
                                    >Por defecto</span
                                >
                                <span
                                    v-else
                                    class="font-medium text-indigo-600 dark:text-indigo-400"
                                    >{{ createForm.status }}</span
                                >
                                <svg
                                    v-if="createStatusesLoading"
                                    class="w-4 h-4 animate-spin text-indigo-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v8z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="w-4 h-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>
                            <div
                                v-if="showStatusDropdown"
                                class="fixed inset-0 z-40"
                                @click="showStatusDropdown = false"
                            ></div>
                            <div
                                v-if="showStatusDropdown"
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg py-1 max-h-48 overflow-y-auto"
                            >
                                <button
                                    type="button"
                                    @click="
                                        createForm.status = '';
                                        showStatusDropdown = false;
                                    "
                                    class="w-full px-3 py-2 text-sm hover:bg-gray-50 text-gray-400 text-left italic"
                                >
                                    Defecto de Jira
                                </button>
                                <template v-if="selectableStatuses.length">
                                    <button
                                        v-for="s in selectableStatuses"
                                        :key="s"
                                        type="button"
                                        @click="
                                            createForm.status = s;
                                            showStatusDropdown = false;
                                        "
                                        class="w-full px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-gray-700 dark:text-gray-200 text-left font-medium"
                                    >
                                        {{ s }}
                                    </button>
                                </template>
                                <template v-else>
                                    <div
                                        class="px-3 py-3 text-center text-xs text-gray-400"
                                    >
                                        {{
                                            createStatusesLoading
                                                ? 'Cargando estados...'
                                                : 'No hay estados disponibles'
                                        }}
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Solicitado Por (buscador dinámico) -->
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                >Solicitado Por</label
                            >
                            <div class="relative">
                                <!-- Avatar seleccionado -->
                                <div
                                    v-if="selectedReporterObj"
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 flex items-center pointer-events-none"
                                >
                                    <img
                                        v-if="
                                            selectedReporterObj.avatar_url
                                        "
                                        :src="
                                            selectedReporterObj.avatar_url
                                        "
                                        class="w-5 h-5 rounded-full"
                                    />
                                    <span
                                        v-else
                                        class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-bold flex items-center justify-center"
                                        >{{
                                            initials(
                                                selectedReporterObj.display_name
                                            )
                                        }}</span
                                    >
                                </div>
                                <input
                                    type="text"
                                    :value="
                                        createForm._reporter_display
                                    "
                                    @input="$emit('reporter-input', $event)"
                                    @focus="$emit('reporter-focus')"
                                    placeholder="Buscar solicitante..."
                                    autocomplete="off"
                                    :class="[
                                        'w-full py-2 pr-8 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40',
                                        selectedReporterObj
                                            ? 'pl-9'
                                            : 'px-3',
                                    ]"
                                />
                                <svg
                                    v-if="reporterLoading"
                                    class="animate-spin absolute right-2 top-2.5 w-4 h-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                            </div>

                            <!-- Click-away Overlay -->
                            <div
                                v-if="reporterSuggestions.length"
                                class="fixed inset-0 z-10"
                                @click="$emit('clear-reporter-suggestions')"
                            ></div>

                            <ul
                                v-if="reporterSuggestions.length"
                                class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg overflow-hidden max-h-48 overflow-y-auto"
                            >
                                <li
                                    v-for="u in reporterSuggestions"
                                    :key="u.account_id"
                                    @mousedown.prevent="$emit('select-reporter', u)"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                >
                                    <img
                                        v-if="u.avatar_url"
                                        :src="u.avatar_url"
                                        loading="lazy"
                                        class="w-6 h-6 rounded-full shrink-0"
                                        alt=""
                                    />
                                    <span
                                        v-else
                                        class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0"
                                        >{{
                                            initials(u.display_name)
                                        }}</span
                                    >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ u.display_name }}
                                        </p>
                                        <p
                                            v-if="u.email"
                                            class="text-[10px] text-gray-400 truncate"
                                        >
                                            {{ u.email }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                            <p
                                v-if="createForm.reporter_account_id"
                                class="mt-1 text-[11px] text-green-600 dark:text-green-400"
                            >
                                ✓ Solicitante seleccionado
                            </p>
                            <p
                                v-else
                                class="mt-1 text-[10px] text-gray-400"
                            >
                                Si se deja vacío, el creador será
                                asignado como solicitante automáticamente
                            </p>
                        </div>

                        <!-- Categoría (opcional): catálogo del espacio que crece solo -->
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                >Categoría</label
                            >
                            <div class="relative">
                                <input
                                    v-model="createForm.categoria"
                                    type="text"
                                    maxlength="100"
                                    autocomplete="off"
                                    placeholder="Elige una o escribe una nueva..."
                                    @focus="showCategoriaSugg = true"
                                    class="w-full px-3 py-2 pr-8 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                                />
                                <button
                                    v-if="createForm.categoria"
                                    type="button"
                                    @click="pickCategoria('')"
                                    title="Limpiar categoría"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <!-- Click-away Overlay -->
                            <div
                                v-if="showCategoriaSugg && filteredCategorias.length"
                                class="fixed inset-0 z-10"
                                @click="showCategoriaSugg = false"
                            ></div>

                            <ul
                                v-if="showCategoriaSugg && filteredCategorias.length"
                                class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg overflow-hidden max-h-48 overflow-y-auto custom-scrollbar"
                            >
                                <li
                                    v-for="c in filteredCategorias"
                                    :key="c"
                                    @mousedown.prevent="pickCategoria(c)"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                >
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-sm border border-indigo-500 bg-indigo-500 text-white dark:border-indigo-600 dark:bg-indigo-600 dark:text-white truncate">{{ c }}</span>
                                </li>
                            </ul>
                            <p class="mt-1 text-[10px] text-gray-400">
                                Opcional. Elige una categoría del espacio o escribe una nueva (se guardará en este espacio).
                            </p>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                >Descripción</label
                            >
                            <textarea
                                v-model="createForm.description"
                                rows="3"
                                maxlength="10000"
                                placeholder="Detalla el alcance, contexto o pasos para reproducir..."
                                class="w-full px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 resize-none"
                            />
                        </div>
                    </div>

                    <!-- ══════ PANEL: Alcance ══════ -->
                    <div v-show="activeTab === 'alcance'" class="space-y-4">
                        <!-- Prioridad -->
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                >Prioridad</label
                            >

                            <!-- Dropdown Trigger -->
                            <button
                                type="button"
                                @click="
                                    showPriorityDropdown =
                                        !showPriorityDropdown
                                "
                                class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            >
                                <div class="flex items-center gap-2">
                                    <svg
                                        v-if="createForm.priority"
                                        class="w-3.5 h-3.5 shrink-0"
                                        :class="severityIcon(createForm.priority).cls"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                    ><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(createForm.priority).path" /></svg>
                                    <span
                                        v-if="createForm.priority"
                                        class="font-medium"
                                        >{{ createForm.priority }}</span
                                    >
                                    <span v-else class="text-gray-400"
                                        >Sin prioridad</span
                                    >
                                </div>
                                <svg
                                    class="w-4 h-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>

                            <!-- Click-away Overlay -->
                            <div
                                v-if="showPriorityDropdown"
                                class="fixed inset-0 z-40"
                                @click="showPriorityDropdown = false"
                            ></div>

                            <!-- Dropdown Menu -->
                            <div
                                v-if="showPriorityDropdown"
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg py-1 max-h-60 overflow-y-auto"
                            >
                                <button
                                    type="button"
                                    @click="
                                        createForm.priority = '';
                                        showPriorityDropdown = false;
                                    "
                                    class="w-full px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 text-left transition-colors"
                                >
                                    Sin prioridad
                                </button>
                                <button
                                    v-for="p in priorityOptions ||
                                    []"
                                    :key="p"
                                    type="button"
                                    @click="
                                        createForm.priority = p;
                                        showPriorityDropdown = false;
                                    "
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-gray-700 dark:text-gray-200 text-left transition-colors font-medium"
                                >
                                    <svg class="w-3.5 h-3.5 shrink-0" :class="severityIcon(p).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(p).path" /></svg>
                                    {{ p }}
                                </button>
                            </div>
                        </div>

                        <!-- Impacto -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Impacto</label>
                        <div class="flex flex-wrap gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <button
                                v-for="tag in impactoOptions"
                                :key="tag"
                                type="button"
                                @click="
                                    createForm.impacto = createForm.impacto.includes(tag)
                                        ? []
                                        : [tag]
                                "
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded border transition-all',
                                    createForm.impacto.includes(tag)
                                        ? 'bg-indigo-600 text-white border-indigo-700 shadow-md scale-105'
                                        : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-indigo-300',
                                ]"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0" :class="createForm.impacto.includes(tag) ? 'text-white' : severityIcon(tag).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(tag).path" /></svg>
                                {{ tag }}
                            </button>
                        </div>
                        </div>

                        <!-- Asignado (autocomplete con usuarios reales del proyecto) -->
                        <div class="relative">
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                            >
                                Persona Asignada
                                <svg
                                    v-if="createAssigneesLoading"
                                    class="inline animate-spin w-3 h-3 ml-1 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                            </label>
                            <div class="relative">
                                <!-- Avatar seleccionado -->
                                <div
                                    v-if="selectedAssigneeObj"
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 flex items-center pointer-events-none"
                                >
                                    <img
                                        v-if="
                                            selectedAssigneeObj.avatar_url
                                        "
                                        :src="
                                            selectedAssigneeObj.avatar_url
                                        "
                                        class="w-5 h-5 rounded-full"
                                    />
                                    <span
                                        v-else
                                        class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-bold flex items-center justify-center"
                                        >{{
                                            initials(
                                                selectedAssigneeObj.display_name
                                            )
                                        }}</span
                                    >
                                </div>
                                <input
                                    type="text"
                                    :value="
                                        createForm._assignee_display
                                    "
                                    @input="$emit('assignee-input', $event)"
                                    @focus="$emit('assignee-focus')"
                                    placeholder="Buscar usuario..."
                                    autocomplete="off"
                                    :class="[
                                        'w-full py-2 pr-8 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40',
                                        selectedAssigneeObj
                                            ? 'pl-9'
                                            : 'px-3',
                                    ]"
                                />
                                <svg
                                    v-if="assigneeLoading"
                                    class="animate-spin absolute right-2 top-2.5 w-4 h-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                            </div>

                            <!-- Click-away Overlay -->
                            <div
                                v-if="assigneeSuggestions.length"
                                class="fixed inset-0 z-10"
                                @click="$emit('clear-assignee-suggestions')"
                            ></div>

                            <ul
                                v-if="assigneeSuggestions.length"
                                class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-sm shadow-lg overflow-hidden max-h-48 overflow-y-auto"
                            >
                                <li
                                    v-for="u in assigneeSuggestions"
                                    :key="u.account_id"
                                    @mousedown.prevent="$emit('select-assignee', u)"
                                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                >
                                    <img
                                        v-if="u.avatar_url"
                                        :src="u.avatar_url"
                                        loading="lazy"
                                        class="w-6 h-6 rounded-full shrink-0"
                                        alt=""
                                    />
                                    <span
                                        v-else
                                        class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0"
                                        >{{
                                            initials(u.display_name)
                                        }}</span
                                    >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ u.display_name }}
                                        </p>
                                        <p
                                            v-if="u.email"
                                            class="text-[10px] text-gray-400 truncate"
                                        >
                                            {{ u.email }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                            <p
                                v-if="createForm.assignee_account_id"
                                class="mt-1 text-[11px] text-green-600 dark:text-green-400"
                            >
                                ✓ Asignado seleccionado
                            </p>
                            <p
                                v-else
                                class="mt-1 text-[10px] text-gray-400"
                            >
                                Si se deja vacío, se asigna
                                automáticamente al creador
                            </p>
                        </div>

                        <!-- Equipo -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Equipo</label>
                            <select
                                v-model="createForm.team_id"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            >
                                <option value="">Seleccionar equipo...</option>
                                <option v-for="t in spaceTeams" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>

                    <!-- Etiquetas -->
                    <div>
                        <div
                            class="flex items-center justify-between mb-2"
                        >
                            <label
                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                >Etiquetas</label
                            >
                            <button
                                v-if="createForm.labels.length > 0"
                                type="button"
                                @click="createForm.labels = []"
                                class="flex items-center gap-1.5 px-2 py-1 text-[10px] font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors uppercase tracking-wider"
                                title="Limpiar todas las etiquetas"
                            >
                                <svg
                                    class="w-3.5 h-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M3 20h18" />
                                    <path
                                        d="M4.427 13.923a2 2 0 0 1 0-2.828l9.9-9.9a2 2 0 0 1 2.828 0l3.536 3.536a2 2 0 0 1 0 2.828l-9.9 9.9a2 2 0 0 1-2.828 0z"
                                    />
                                    <path d="m8.5 8.5 7 7" />
                                </svg>
                                Limpiar
                            </button>
                        </div>
                        <div
                            class="flex flex-wrap gap-2 max-h-32 overflow-y-auto custom-scrollbar p-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50"
                        >
                            <span
                                v-if="createLabelsLoading"
                                class="text-xs text-gray-400 italic flex items-center gap-1"
                            >
                                <svg
                                    class="w-3 h-3 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v8z"
                                    />
                                </svg>
                                Cargando etiquetas...
                            </span>
                            <span
                                v-else-if="!availableLabels.length"
                                class="text-xs text-gray-400 italic"
                                >No hay etiquetas. Crea una abajo.</span
                            >
                            <button
                                v-for="label in availableLabels"
                                :key="label"
                                type="button"
                                @click="
                                    toggleLabel(label)
                                "
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider rounded-md border transition-colors duration-150',
                                    createForm.labels.includes(label)
                                        ? 'bg-indigo-600 text-white border-indigo-600 dark:bg-indigo-500 dark:border-indigo-500 shadow-md ring-2 ring-indigo-300 dark:ring-indigo-400/40 ring-offset-1 dark:ring-offset-zinc-900 scale-105'
                                        : 'opacity-90 hover:opacity-100 ' +
                                          getLabelColor(label),
                                ]"
                            >
                                {{ label }}
                            </button>
                        </div>
                        <!-- Crear etiqueta al vuelo -->
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                v-model="newLabelName"
                                @keyup.enter.prevent="addNewLabel"
                                type="text"
                                maxlength="100"
                                placeholder="Nueva etiqueta…"
                                class="flex-1 px-2.5 py-2.5 text-xs rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            />
                            <button
                                type="button"
                                @click="addNewLabel"
                                :disabled="!newLabelName.trim()"
                                class="inline-flex items-center gap-1 px-3 py-2.5 text-xs font-semibold rounded-sm bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition"
                            >
                                + Agregar
                            </button>
                        </div>
                        <p class="mt-1 text-[10px] text-gray-400">
                            Selecciona etiquetas del espacio o crea una nueva (se guardará en este espacio).
                        </p>
                    </div>
                    </div>

                    <!-- ══════ PANEL: Fechas ══════ -->
                    <div v-show="activeTab === 'fechas'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Inicio -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                    >Inicio <span class="text-red-500">*</span></label
                                >
                                <input
                                    v-model="createForm.start_date"
                                    :type="
                                        createForm.start_date
                                            ? 'date'
                                            : 'text'
                                    "
                                    :min="bloquearFechas ? (parentStartDate || todayYmd) : undefined"
                                    @focus="
                                        $event.target.type = 'date';
                                        $event.target.showPicker();
                                    "
                                    @blur="
                                        !createForm.start_date &&
                                        ($event.target.type = 'text')
                                    "
                                    placeholder="Elegir fecha"
                                    class="w-full px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 cursor-pointer"
                                />
                                <p v-if="bloquearFechas && parentStartDate" class="mt-1 text-[10px] text-gray-400">
                                    No puede ser anterior a la actividad padre ({{ parentStartDate }}).
                                </p>
                                <p v-else-if="bloquearFechas" class="mt-1 text-[10px] text-gray-400">
                                    No puede ser anterior a hoy.
                                </p>
                                <p v-else class="mt-1 text-[10px] text-emerald-500/80">
                                    Fecha de inicio libre (cualquier fecha).
                                </p>
                            </div>

                            <!-- Fecha Límite -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                    >Fecha Límite</label
                                >
                                <input
                                    v-model="createForm.fecha_limite"
                                    :type="
                                        createForm.fecha_limite
                                            ? 'date'
                                            : 'text'
                                    "
                                    :min="createForm.start_date || todayYmd"
                                    @focus="
                                        $event.target.type = 'date';
                                        $event.target.showPicker();
                                    "
                                    @blur="
                                        !createForm.fecha_limite &&
                                        ($event.target.type = 'text')
                                    "
                                    placeholder="Elegir fecha"
                                    class="w-full px-3 py-2 text-sm rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 cursor-pointer"
                                />
                            </div>

                            <!-- Las fechas de Subida a Stage/Producción ya NO se cargan a mano:
                                 se auto-asignan al pasar la actividad a "En Revisión" / "Finalizado". -->
                        </div>

                        <!-- Días Estimados: aparece solo cuando hay Fecha Límite (se sincroniza con ella) -->
                        <div v-if="createForm.start_date && createForm.fecha_limite">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Días Estimados</label>
                            <input
                                v-model="createForm.dias_estimados"
                                type="number"
                                min="0"
                                max="9999"
                                placeholder="Nº de días"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                            />
                            <p class="mt-1 text-[10px] text-gray-400">
                                Calculado automáticamente desde la Fecha Límite; puedes ajustarlo.
                            </p>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer (Fijo) - Updated with ModalForm pattern -->
            <div
                class="sticky bottom-0 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl px-8 py-4 border-t border-gray-200/70 dark:border-gray-700/70 shadow-lg shrink-0"
            >
                <div class="flex items-center justify-end gap-3">
                    <!-- Hint text (left side) -->
                    <p
                        v-if="previewReady"
                        class="text-[11px] text-gray-600 dark:text-gray-400 italic mr-auto"
                    >
                        Revisa tu issue antes de confirmar.
                    </p>

                    <!-- Cancel button -->
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="group relative px-6 py-2.5 rounded-xl font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-300/50 dark:border-gray-600/50 transition-all duration-200 shadow-sm hover:shadow-md"
                    >
                        <span
                            class="relative z-10 flex items-center gap-2"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                            Cancelar
                        </span>
                    </button>

                    <!-- Create button with gradient -->
                    <button
                        type="submit"
                        form="jira-create-form"
                        :disabled="createForm.processing || submitting"
                        class="group relative px-8 py-2.5 rounded-xl font-bold text-white bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-600 hover:from-blue-700 hover:via-indigo-700 hover:to-purple-700 dark:from-blue-500 dark:via-indigo-500 dark:to-cyan-500 dark:hover:from-blue-600 dark:hover:via-indigo-600 dark:hover:to-purple-600 shadow-lg hover:shadow-xl hover:shadow-indigo-500/50 dark:hover:shadow-indigo-400/30 border border-blue-700/20 dark:border-blue-400/20 transition-all duration-300 disabled:opacity-70 disabled:cursor-not-allowed overflow-hidden"
                        :class="{
                            'hover:scale-105': !(createForm.processing || submitting),
                        }"
                    >
                        <!-- Shine effect -->
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent translate-x-[-200%] group-hover:translate-x-[200%] transition-transform duration-1000"
                        ></div>

                        <span
                            class="relative z-10 flex items-center justify-center gap-2"
                        >
                            <template v-if="createForm.processing || submitting">
                                <svg
                                    class="w-5 h-5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                <span>Procesando...</span>
                            </template>
                            <template v-else>
                                <svg
                                    class="w-5 h-5 transition-transform group-hover:scale-110"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    stroke-width="2.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <span>Crear</span>
                            </template>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</Teleport>
</template>
