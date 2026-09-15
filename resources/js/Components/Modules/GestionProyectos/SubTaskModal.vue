<script setup>
import { ref, watch, computed, reactive } from 'vue';
import Actions from '@/Components/Utilities/Actions.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import FileUploader from '@/Components/Common/FileUploader.vue';
import { severityIcon } from '@/Composables/GestionProyectos/useSeverityIcon';
import { useAssignableUserSearch } from '@/Composables/GestionProyectos/useAssignableUserSearch';

// ─── Props / Emits ────────────────────────────────────────────────────────────
const props = defineProps({
    show:           { type: Boolean,  default: false },
    issueKey:       { type: String,   default: null  },
    issueSummary:   { type: String,   default: ''    },
    projectKey:     { type: String,   default: ''    },
    availableUsers: { type: Array,    default: () => [] },
    canEdit:        { type: Boolean,  default: false },  // crear / editar / eliminar tareas
    canApprove:     { type: Boolean,  default: false },  // SOLO el aprobador puede finalizar
});

const emit = defineEmits(['update:show', 'tareas-updated']);

// Cada tarea tiene Título + Descripción. Finalizar exige evidencia (comentario +
// adjunto) y solo lo puede hacer el rol aprobador. Finalizado es terminal.
const dirty = ref(false);
function close() {
    if (dirty.value && props.issueKey) emit('tareas-updated', props.issueKey);
    dirty.value = false;
    emit('update:show', false);
}

const isSubactividad = computed(() => /-S\d+$/.test(props.issueKey || ''));

// ─── Estado ───────────────────────────────────────────────────────────────────
const tareas       = ref([]);
const loading      = ref(false);
const error        = ref('');
const currentIndex = ref(0);

const currentTarea = computed(() => tareas.value[currentIndex.value] || null);
const isFinalizada = computed(() => currentTarea.value?.status === 'Finalizado');
const doneCount    = computed(() => tareas.value.filter(t => t.status === 'Finalizado').length);

// ─── Carga ──────────────────────────────────────────────────────────────────
async function fetchTareas() {
    const { data } = await window.axios.get(route('gestion-proyectos.subtareas.index', { key: props.issueKey }));
    tareas.value = data;
    if (currentIndex.value > data.length - 1) currentIndex.value = Math.max(0, data.length - 1);
}

async function loadAll() {
    if (!props.issueKey) return;
    loading.value = true; error.value = '';
    try {
        await fetchTareas();
        await loadHistorial(currentTarea.value);
    } catch (e) {
        error.value = e.response ? 'Error al cargar las tareas.' : 'Error de conexión.';
    } finally {
        loading.value = false;
    }
}

watch(() => props.show, (val) => {
    if (val && props.issueKey) {
        tareas.value = [];
        currentIndex.value = 0;
        dirty.value = false;
        closeForm();
        closeFinalizar();
        loadAll();
    }
});

// Al cambiar de página (tarea), refresco su historial.
watch(currentIndex, () => loadHistorial(currentTarea.value));

// ─── Paginación horizontal ────────────────────────────────────────────────────
function prev() { if (currentIndex.value > 0) currentIndex.value--; }
function next() { if (currentIndex.value < tareas.value.length - 1) currentIndex.value++; }

// ─── Historial / evidencia de la tarea actual ─────────────────────────────────
const historial        = ref([]);
const historialLoading = ref(false);

async function loadHistorial(t) {
    if (!t) { historial.value = []; return; }
    historialLoading.value = true;
    try {
        const { data } = await window.axios.get(route('gestion-proyectos.subtareas.historial.index', { id: t.id }));
        historial.value = data;
    } catch {
        historial.value = [];
    } finally {
        historialLoading.value = false;
    }
}

// ─── Crear / editar tarea ─────────────────────────────────────────────────────
const form   = reactive({ open: false, inline: false, editId: null, summary: '', description: '', assignee_id: '', priority: '' });
const saving = ref(false);

const showAssigneeDropdown = ref(false);
const showPriorityDropdown = ref(false);

// Búsqueda server-side de usuarios (alcanza a cualquier miembro, no solo los 200 iniciales).
const { query: userQuery, results: userResults, loading: usersLoading, search: searchUsers, clear: clearUserSearch } = useAssignableUserSearch();
const pickedAssignee = ref(null);
const displayUsers = computed(() => (userQuery.value.trim() ? userResults.value : props.availableUsers));

function pickAssignee(u) {
    form.assignee_id = u.account_id;
    pickedAssignee.value = u;
    showAssigneeDropdown.value = false;
    clearUserSearch();
}

const selectedAssigneeUser = computed(() => {
    if (!form.assignee_id) return null;
    const id = String(form.assignee_id);
    // Buscar en la lista inicial + resultados de búsqueda; si la búsqueda lo filtró, usar el
    // usuario cacheado (al elegirlo o al abrir en edición) para que siempre se muestre bien.
    return [...props.availableUsers, ...userResults.value].find(u => String(u.account_id) === id)
        ?? (pickedAssignee.value && String(pickedAssignee.value.account_id) === id ? pickedAssignee.value : null);
});

function initials(name) {
    return (name || '?').split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();
}

// Edición inline de la tarea actual (en vez de cambiar a otra UI).
const editingCurrent = computed(() =>
    form.open && form.inline && currentTarea.value && form.editId === currentTarea.value.id
);

function openCreate() {
    Object.assign(form, { open: true, inline: false, editId: null, summary: '', description: '', assignee_id: '', priority: '' });
    pickedAssignee.value = null; clearUserSearch();
    closeFinalizar(); error.value = '';
}
function openEdit(t) {
    // Edición inline: rellena el form pero se renderiza sobre la propia tarea.
    Object.assign(form, {
        open: true, inline: true, editId: t.id, summary: t.summary ?? '', description: t.description ?? '',
        assignee_id: t.assignee ? String(t.assignee.account_id) : '', priority: t.priority ?? '',
    });
    // Cachear el asignado actual para mostrarlo aunque la búsqueda filtre la lista.
    pickedAssignee.value = t.assignee
        ? { account_id: String(t.assignee.account_id), display_name: t.assignee.display_name, avatar_url: t.assignee.avatar_url ?? null }
        : null;
    clearUserSearch();
    closeFinalizar(); error.value = '';
}
function closeForm() { form.open = false; form.inline = false; form.editId = null; }

async function saveTarea() {
    if (!form.summary.trim()) { error.value = 'El título de la tarea es obligatorio.'; return; }
    saving.value = true; error.value = '';
    const body = {
        summary: form.summary.trim(),
        description: form.description.trim() || null,
        assignee_id: form.assignee_id ? parseInt(form.assignee_id) : null,
        priority: form.priority || null,
    };
    try {
        const editing = !!form.editId;
        if (editing) {
            await window.axios.patch(route('gestion-proyectos.subtareas.update', { id: form.editId }), body);
        } else {
            await window.axios.post(route('gestion-proyectos.subtareas.store', { key: props.issueKey }), { ...body, status: 'Pendiente' });
        }
        await fetchTareas();
        if (!editing) currentIndex.value = tareas.value.length - 1; // saltar a la nueva
        await loadHistorial(currentTarea.value);
        dirty.value = true;
        closeForm();
        window.showToast?.(editing ? 'Tarea actualizada.' : 'Tarea creada.', 'success', { timer: 2000 });
    } catch (e) {
        error.value = e.response ? (e.response.data?.message || 'Error al guardar la tarea.') : 'Error de conexión.';
    } finally { saving.value = false; }
}

async function deleteTarea(t) {
    if (!confirm(`¿Eliminar la tarea "${t.summary}"?`)) return;
    try {
        await window.axios.delete(route('gestion-proyectos.subtareas.destroy', { id: t.id }));
        await fetchTareas();
        await loadHistorial(currentTarea.value);
        dirty.value = true;
    } catch { window.showToast?.('No se pudo eliminar la tarea.', 'error', { timer: 2500 }); }
}

// ─── Finalizar con evidencia (comentario + adjuntos) — solo aprobador ──────────
// Los adjuntos los maneja <FileUploader> vía v-model="evidFiles" (array de File). Máx. 3.
const MAX_EVID_FILES = 3;
const evid      = reactive({ open: false, comment: '' });
const evidFiles = ref([]);
const evidSaving = ref(false);

// Límite de 3 archivos (el FileUploader es compartido y no se toca: se acota aquí).
watch(evidFiles, (files) => {
    if (Array.isArray(files) && files.length > MAX_EVID_FILES) {
        evidFiles.value = files.slice(0, MAX_EVID_FILES);
        window.showToast?.(`Máximo ${MAX_EVID_FILES} archivos por evidencia.`, 'warning', { timer: 2500 });
    }
});

function openFinalizar() {
    Object.assign(evid, { open: true, comment: '' });
    evidFiles.value = []; closeForm(); error.value = '';
}
function closeFinalizar() { evid.open = false; evidFiles.value = []; }

async function confirmarFinalizar() {
    const t = currentTarea.value;
    if (!t) return;
    if (!evid.comment.trim()) { error.value = 'El comentario de evidencia es obligatorio para finalizar.'; return; }
    evidSaving.value = true; error.value = '';
    try {
        // 1) Registrar evidencia (comentario + hasta 3 adjuntos) en el historial de la tarea.
        const fd = new FormData();
        fd.append('comment', evid.comment.trim());
        (evidFiles.value || []).forEach((f) => fd.append('attachment[]', f));
        await window.axios.post(route('gestion-proyectos.subtareas.historial.store', { id: t.id }), fd);

        // 2) Finalizar la tarea (el backend valida rol aprobador + evidencia).
        await window.axios.patch(route('gestion-proyectos.subtareas.update', { id: t.id }), { status: 'Finalizado' });

        t.status = 'Finalizado';
        dirty.value = true;
        closeFinalizar();
        await loadHistorial(t);
        window.showToast?.('Tarea finalizada.', 'success', { timer: 2000 });
    } catch (e) {
        const r = e.response;
        error.value = r?.data?.error || r?.data?.message || 'No se pudo finalizar la tarea.';
    } finally { evidSaving.value = false; }
}

// ─── Estilos ──────────────────────────────────────────────────────────────────
function priorityClass(p) {
    if (p === 'Alta')  return 'bg-red-500 text-white border-red-600';
    if (p === 'Media') return 'bg-amber-500 text-white border-amber-600';
    if (p === 'Baja')  return 'bg-blue-500 text-white border-blue-600';
    return 'bg-gray-400 text-white border-gray-500';
}
</script>

<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        :title="isSubactividad ? 'Tareas · Subactividad' : 'Tareas · Actividad'"
        :subtitle="`${issueKey} — ${issueSummary}`"
        size="3xl"
        :hide-footer="!form.open"
        @close="close"
    >
        <!-- Acción del header: nueva tarea -->
        <template #header-actions>
            <button v-if="canEdit && !form.open" type="button" @click="openCreate"
                class="inline-flex items-center gap-2 px-3 py-2 text-[11px] font-semibold rounded-lg bg-white/15 hover:bg-white/25 text-white border border-white/20 transition-colors">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                Nueva tarea
            </button>
        </template>

        <!-- Barra: contador + paginador -->
        <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Tareas</span>
                <span class="inline-flex items-center justify-center min-w-[36px] px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ doneCount }}/{{ tareas.length }}</span>
            </div>
            <!-- Paginador compacto -->
            <div v-if="tareas.length > 1 && !form.open" class="flex items-center gap-0.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-1 py-0.5">
                <button @click="prev" :disabled="currentIndex === 0" class="p-0.5 rounded text-gray-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <span class="text-[10px] font-medium text-gray-500 tabular-nums px-2 py-1.5">{{ currentIndex + 1 }}/{{ tareas.length }}</span>
                <button @click="next" :disabled="currentIndex === tareas.length - 1" class="p-0.5 rounded text-gray-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        </div>

        <!-- ── Body ── -->
        <div>

                        <!-- Error -->
                        <p v-if="error" class="mb-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg px-3 py-2">{{ error }}</p>

                        <!-- Loading -->
                        <div v-if="loading" class="flex items-center justify-center py-14">
                            <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>

                        <template v-else>

                            <!-- ── Formulario crear (mismo estilo visual que la edición inline) ── -->
                            <div v-if="form.open && !form.inline" class="space-y-4">
                                <!-- Título -->
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wide">Título <span class="text-red-500">*</span></label>
                                    <input v-model="form.summary" placeholder="Título breve de la tarea" maxlength="500"
                                        class="w-full text-sm font-semibold rounded-lg border border-indigo-300 dark:border-indigo-500/50 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        @keydown.enter="saveTarea" />
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <!-- Asignado -->
                                    <div class="relative">
                                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1">Asignado</label>
                                        <button type="button" @click="showAssigneeDropdown = !showAssigneeDropdown; showPriorityDropdown = false"
                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <template v-if="selectedAssigneeUser">
                                                    <img v-if="selectedAssigneeUser.avatar_url" :src="selectedAssigneeUser.avatar_url" class="w-5 h-5 rounded-full object-cover shrink-0" />
                                                    <span v-else class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-bold inline-flex items-center justify-center shrink-0">{{ initials(selectedAssigneeUser.display_name) }}</span>
                                                    <span class="truncate">{{ selectedAssigneeUser.display_name }}</span>
                                                </template>
                                                <span v-else class="text-gray-400">— Sin asignar —</span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                        <div v-if="showAssigneeDropdown" class="fixed inset-0 z-40" @click="showAssigneeDropdown = false; clearUserSearch()"></div>
                                        <div v-if="showAssigneeDropdown" class="absolute z-50 w-full top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 max-h-56 overflow-y-auto">
                                            <div class="px-2 pt-1 pb-1.5 sticky top-0 bg-white dark:bg-gray-800">
                                                <input :value="userQuery" @input="searchUsers(projectKey, $event.target.value)" @click.stop type="text" placeholder="Buscar por nombre o email…"
                                                    class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
                                            </div>
                                            <button type="button" @click="form.assignee_id = ''; showAssigneeDropdown = false; clearUserSearch()"
                                                class="w-full px-3 py-2 text-sm text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 text-left transition-colors">— Sin asignar —</button>
                                            <div v-if="usersLoading" class="px-3 py-2 text-xs text-gray-400">Buscando…</div>
                                            <button type="button" v-for="u in displayUsers" :key="u.account_id"
                                                @click="pickAssignee(u)"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-left transition-colors">
                                                <img v-if="u.avatar_url" :src="u.avatar_url" class="w-6 h-6 rounded-full object-cover shrink-0" />
                                                <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold inline-flex items-center justify-center shrink-0">{{ initials(u.display_name) }}</span>
                                                <span class="truncate">{{ u.display_name }}</span>
                                            </button>
                                            <div v-if="!usersLoading && userQuery.trim() && displayUsers.length === 0" class="px-3 py-2 text-xs text-gray-400">Sin resultados.</div>
                                        </div>
                                    </div>
                                    <!-- Prioridad -->
                                    <div class="relative">
                                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1">Prioridad</label>
                                        <button type="button" @click="showPriorityDropdown = !showPriorityDropdown; showAssigneeDropdown = false"
                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <div class="flex items-center gap-2">
                                                <svg v-if="form.priority" class="w-3.5 h-3.5 shrink-0" :class="severityIcon(form.priority).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(form.priority).path" /></svg>
                                                <span v-if="form.priority" class="font-medium">{{ form.priority }}</span>
                                                <span v-else class="text-gray-400">— Sin prioridad —</span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                        <div v-if="showPriorityDropdown" class="fixed inset-0 z-40" @click="showPriorityDropdown = false"></div>
                                        <div v-if="showPriorityDropdown" class="absolute z-50 w-full top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1">
                                            <button type="button" @click="form.priority = ''; showPriorityDropdown = false"
                                                class="w-full px-3 py-2 text-sm text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 text-left transition-colors">— Sin prioridad —</button>
                                            <button type="button" v-for="p in ['Alta', 'Media', 'Baja']" :key="p"
                                                @click="form.priority = p; showPriorityDropdown = false"
                                                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-left transition-colors font-medium">
                                                <svg class="w-3.5 h-3.5 shrink-0" :class="severityIcon(p).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(p).path" /></svg>
                                                {{ p }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Descripción (misma caja con header que en la edición) -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Descripción</span>
                                        <span class="text-[10px] text-gray-400">{{ (form.description || '').length }}/5000</span>
                                    </div>
                                    <div class="p-2">
                                        <textarea v-model="form.description" maxlength="5000" rows="10"
                                            placeholder="Detalle completo de la tarea…"
                                            class="w-full text-[15px] rounded-lg border border-indigo-300 dark:border-indigo-500/50 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-3 py-2 resize-y min-h-[180px] max-h-[28rem] leading-7 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Sin tareas ── -->
                            <div v-else-if="!tareas.length" class="flex flex-col items-center justify-center py-12 gap-2 text-center">
                                <svg class="w-10 h-10 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                <p class="text-sm text-gray-400">Sin tareas todavía</p>
                                <p class="text-xs text-gray-400">Cada tarea lleva un título y descripción detallada.</p>
                            </div>

                            <!-- ── Tarea actual (paginada) ── -->
                            <div v-else-if="currentTarea" :key="currentTarea.id" class="space-y-4">

                                <!-- Título + estado + prioridad -->
                                <div>
                                    <div class="flex items-start gap-2 justify-between mb-1">
                                        <!-- Vista normal -->
                                        <h3 v-if="!editingCurrent" class="text-sm font-semibold flex-1 break-words leading-snug"
                                            :class="isFinalizada ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-100'">
                                            {{ currentTarea.summary }}
                                        </h3>
                                        <!-- Edición inline del título -->
                                        <input v-else v-model="form.summary" maxlength="500" placeholder="Título de la tarea"
                                            class="flex-1 text-sm font-semibold rounded-lg border border-indigo-300 dark:border-indigo-500/50 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            @keydown.enter="saveTarea" />
                                        <div class="flex items-center gap-1.5 shrink-0 mt-0.5">
                                            <span v-if="!editingCurrent && currentTarea.priority" :class="['px-1.5 py-0.5 text-[9px] font-semibold rounded border', priorityClass(currentTarea.priority)]">{{ currentTarea.priority }}</span>
                                            <span v-if="isFinalizada" class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-600 text-white border border-emerald-700">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                Finalizado
                                            </span>
                                            <span v-else-if="!editingCurrent" class="px-2 py-0.5 text-[10px] font-semibold rounded-sm bg-amber-500 text-white border border-amber-600">Pendiente</span>
                                        </div>
                                    </div>
                                    <!-- Vista normal: asignado + creador -->
                                    <div v-if="!editingCurrent && (currentTarea.assignee || currentTarea.creator)" class="flex items-center flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-400">
                                        <span v-if="currentTarea.assignee" class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                            Asignado: <span class="font-medium text-gray-500 dark:text-gray-300">{{ currentTarea.assignee.display_name }}</span>
                                        </span>
                                        <span v-if="currentTarea.creator" class="inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Creado por: <span class="font-medium text-gray-500 dark:text-gray-300">{{ currentTarea.creator.display_name }}</span>
                                        </span>
                                    </div>
                                    <!-- Edición inline: asignado + prioridad -->
                                    <div v-if="editingCurrent" class="grid grid-cols-2 gap-3 mt-2">
                                        <!-- Asignado -->
                                        <div class="relative">
                                            <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1">Asignado</label>
                                            <button type="button" @click="showAssigneeDropdown = !showAssigneeDropdown; showPriorityDropdown = false"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <template v-if="selectedAssigneeUser">
                                                        <img v-if="selectedAssigneeUser.avatar_url" :src="selectedAssigneeUser.avatar_url" class="w-5 h-5 rounded-full object-cover shrink-0" />
                                                        <span v-else class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-bold inline-flex items-center justify-center shrink-0">{{ initials(selectedAssigneeUser.display_name) }}</span>
                                                        <span class="truncate">{{ selectedAssigneeUser.display_name }}</span>
                                                    </template>
                                                    <span v-else class="text-gray-400">— Sin asignar —</span>
                                                </div>
                                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            </button>
                                            <div v-if="showAssigneeDropdown" class="fixed inset-0 z-40" @click="showAssigneeDropdown = false; clearUserSearch()"></div>
                                            <div v-if="showAssigneeDropdown" class="absolute z-50 w-full top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 max-h-56 overflow-y-auto">
                                                <div class="px-2 pt-1 pb-1.5 sticky top-0 bg-white dark:bg-gray-800">
                                                    <input :value="userQuery" @input="searchUsers(projectKey, $event.target.value)" @click.stop type="text" placeholder="Buscar por nombre o email…"
                                                        class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40" />
                                                </div>
                                                <button type="button" @click="form.assignee_id = ''; showAssigneeDropdown = false; clearUserSearch()"
                                                    class="w-full px-3 py-2 text-sm text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 text-left transition-colors">— Sin asignar —</button>
                                                <div v-if="usersLoading" class="px-3 py-2 text-xs text-gray-400">Buscando…</div>
                                                <button type="button" v-for="u in displayUsers" :key="u.account_id"
                                                    @click="pickAssignee(u)"
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-left transition-colors">
                                                    <img v-if="u.avatar_url" :src="u.avatar_url" class="w-6 h-6 rounded-full object-cover shrink-0" />
                                                    <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold inline-flex items-center justify-center shrink-0">{{ initials(u.display_name) }}</span>
                                                    <span class="truncate">{{ u.display_name }}</span>
                                                </button>
                                                <div v-if="!usersLoading && userQuery.trim() && displayUsers.length === 0" class="px-3 py-2 text-xs text-gray-400">Sin resultados.</div>
                                            </div>
                                        </div>
                                        <!-- Prioridad -->
                                        <div class="relative">
                                            <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1">Prioridad</label>
                                            <button type="button" @click="showPriorityDropdown = !showPriorityDropdown; showAssigneeDropdown = false"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <div class="flex items-center gap-2">
                                                    <svg v-if="form.priority" class="w-3.5 h-3.5 shrink-0" :class="severityIcon(form.priority).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(form.priority).path" /></svg>
                                                    <span v-if="form.priority" class="font-medium">{{ form.priority }}</span>
                                                    <span v-else class="text-gray-400">— Sin prioridad —</span>
                                                </div>
                                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                            </button>
                                            <div v-if="showPriorityDropdown" class="fixed inset-0 z-40" @click="showPriorityDropdown = false"></div>
                                            <div v-if="showPriorityDropdown" class="absolute z-50 w-full top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1">
                                                <button type="button" @click="form.priority = ''; showPriorityDropdown = false"
                                                    class="w-full px-3 py-2 text-sm text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 text-left transition-colors">— Sin prioridad —</button>
                                                <button type="button" v-for="p in ['Alta', 'Media', 'Baja']" :key="p"
                                                    @click="form.priority = p; showPriorityDropdown = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-left transition-colors font-medium">
                                                    <svg class="w-3.5 h-3.5 shrink-0" :class="severityIcon(p).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(p).path" /></svg>
                                                    {{ p }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Acciones (vista normal; se ocultan mientras se captura evidencia) -->
                                <div v-if="!editingCurrent && !evid.open" class="flex items-center gap-2 flex-wrap">
                                    <button v-if="!isFinalizada && canApprove && !evid.open" @click="openFinalizar"
                                        title="Finalizar la tarea (requiere evidencia)"
                                        class="inline-flex items-center justify-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white shadow-sm hover:shadow transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        Finalizar
                                    </button>
                                    <button v-if="!isFinalizada && canEdit" @click="openEdit(currentTarea)"
                                        title="Editar la tarea"
                                        class="inline-flex items-center justify-center gap-1.5 h-9 px-4 text-sm font-medium rounded-lg border border-indigo-200 dark:border-indigo-500/40 text-indigo-700 dark:text-indigo-300 bg-white dark:bg-transparent hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        Editar
                                    </button>
                                    <Actions v-if="canEdit"
                                        :remove="true"
                                        removeTitle="Eliminar la tarea"
                                        @delete="deleteTarea(currentTarea)" />
                                </div>

                                <!-- Los botones de guardar/cancelar del editar inline van en el footer del modal -->

                                <!-- Aviso (en su propia línea): solo en vista normal cuando no es aprobador -->
                                <p v-if="!editingCurrent && !isFinalizada && !canApprove" class="text-[11px] text-gray-400 italic -mt-2">Solo el aprobador puede finalizar.</p>

                                <!-- Formulario evidencia para finalizar -->
                                <div v-if="evid.open" class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/5 space-y-3">
                                    <p class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">Evidencia para finalizar</p>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wide">Comentario <span class="text-red-500">*</span></label>
                                        <textarea v-model="evid.comment" rows="3" maxlength="5000"
                                            placeholder="Comentario de evidencia (obligatorio)…"
                                            class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-3 py-2 resize-y min-h-[72px] focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                                    </div>
                                    <!-- Adjunto: mismo componente que el resto del módulo -->
                                    <div>
                                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wide">Adjuntar <span class="font-normal normal-case text-gray-400">(opcional, máx. 3 archivos)</span></label>
                                        <FileUploader v-model="evidFiles" mode="multiple" preset="mixed" :max-size="20480" />
                                    </div>
                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <button @click="closeFinalizar" :disabled="evidSaving" class="inline-flex items-center justify-center h-9 px-4 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50">Cancelar</button>
                                        <button @click="confirmarFinalizar" :disabled="evidSaving || !evid.comment.trim()" class="inline-flex items-center justify-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg v-if="evidSaving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                            {{ evidSaving ? 'Guardando…' : 'Registrar y finalizar' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Descripción: con tope de altura y scroll -->
                                <div v-if="currentTarea.description || editingCurrent" class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Descripción</span>
                                        <span v-if="editingCurrent" class="text-[10px] text-gray-400">{{ (form.description || '').length }}/5000</span>
                                    </div>
                                    <!-- Vista normal -->
                                    <div v-if="!editingCurrent" class="px-4 py-3 max-h-[28rem] overflow-y-auto">
                                        <p class="text-[15px] text-gray-800 dark:text-gray-100 whitespace-pre-line break-words leading-7">{{ currentTarea.description }}</p>
                                    </div>
                                    <!-- Edición inline -->
                                    <div v-else class="p-2">
                                        <textarea v-model="form.description" maxlength="5000" rows="10"
                                            placeholder="Detalle completo de la tarea…"
                                            class="w-full text-[15px] rounded-lg border border-indigo-300 dark:border-indigo-500/50 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 px-3 py-2 resize-y min-h-[180px] max-h-[28rem] leading-7 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                    </div>
                                </div>

                                <!-- Historial de evidencia -->
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">Historial de evidencia</p>
                                    <div v-if="historialLoading" class="text-xs text-gray-400 italic py-2">Cargando…</div>
                                    <ul v-else-if="historial.length" class="space-y-3">
                                        <li v-for="h in historial" :key="h.id" class="flex gap-2.5 text-xs">
                                            <div class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 text-[9px] font-bold mt-0.5">
                                                {{ (h.user_name || '?').substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-baseline gap-1.5 mb-0.5">
                                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ h.user_name }}</span>
                                                    <span class="text-[10px] text-gray-400">{{ h.created_at }}</span>
                                                </div>
                                                <p class="text-gray-600 dark:text-gray-300 whitespace-pre-line break-words leading-relaxed">{{ h.comment }}</p>
                                                <div v-if="h.attachments?.length" class="mt-1 flex flex-col gap-0.5">
                                                    <a v-for="(att, i) in h.attachments" :key="i" :href="att.url" target="_blank" rel="noopener noreferrer"
                                                        class="inline-flex items-center gap-1 text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline">
                                                        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                                        {{ att.name || 'Adjunto' }}
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                    <p v-else class="text-xs text-gray-400 italic">Sin evidencia registrada.</p>
                                </div>

                            </div>
                        </template>
                    </div>

        <!-- Footer compartido por crear y editar tarea (visible mientras form.open, ver :hide-footer) -->
        <template #footer>
            <button @click="closeForm" :disabled="saving"
                class="inline-flex items-center justify-center gap-1.5 h-9 px-4 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all focus:outline-none focus:ring-2 focus:ring-gray-400 disabled:opacity-50">
                Cancelar
            </button>
            <button @click="saveTarea" :disabled="saving || !form.summary.trim()"
                class="inline-flex items-center justify-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg v-if="saving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                {{ saving ? 'Guardando…' : (form.editId ? 'Guardar cambios' : 'Guardar tarea') }}
            </button>
        </template>
    </ModalView>
</template>
