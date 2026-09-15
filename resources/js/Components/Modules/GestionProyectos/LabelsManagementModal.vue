<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Gestión de Etiquetas"
        size="2xl"
        :hide-footer="true"
        @close="close"
    >
        <template #subtitle>
            Espacio
            <span class="font-mono font-semibold text-white/90">{{ projectKey }}</span>
            <span class="opacity-70">·</span>
            {{ localLabels.length }} etiqueta{{ localLabels.length !== 1 ? 's' : '' }}
        </template>

        <div class="space-y-4">
            <!-- ── Barra de secciones (tabs de subrayado) ── -->
            <div class="flex items-end border-b border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    @click="activeTab = 'etiquetas'"
                    :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-1.5', activeTab === 'etiquetas' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                >
                    Etiquetas
                    <span v-if="localLabels.length" class="flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] font-bold rounded-full bg-indigo-500 text-white">{{ localLabels.length }}</span>
                </button>
                <button
                    v-if="canEdit"
                    type="button"
                    @click="activeTab = 'heredar'"
                    :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'heredar' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                >
                    Heredar
                </button>
            </div>

            <!-- ════ TAB: ETIQUETAS (crear + lista) ════ -->
            <div v-show="activeTab === 'etiquetas'" class="space-y-4">
                <!-- Crear etiqueta -->
                <div v-if="canEdit" class="flex items-center gap-2">
                    <input
                        v-model="newName"
                        @keyup.enter="createLabel"
                        type="text"
                        maxlength="100"
                        placeholder="Nueva etiqueta…"
                        class="flex-1 px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    />
                    <button
                        type="button"
                        @click="createLabel"
                        :disabled="!newName.trim() || saving"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Crear
                    </button>
                </div>

                <!-- Lista de etiquetas -->
                <div v-if="loading" class="text-center text-sm text-gray-400 py-6">Cargando…</div>
                <div v-else-if="!localLabels.length" class="text-center text-sm text-gray-400 py-6">
                    Este espacio aún no tiene etiquetas.
                </div>
                <div v-else class="flex flex-wrap gap-2">
                    <div
                        v-for="label in localLabels"
                        :key="label.id"
                        :class="['inline-flex items-center gap-1.5 px-2.5 py-1 text-[12px] font-bold uppercase tracking-wider rounded-md border', getLabelColor(label.name)]"
                    >
                        <!-- Edición inline -->
                        <template v-if="editingId === label.id">
                            <input
                                v-model="editName"
                                @keyup.enter="saveEdit(label)"
                                @keyup.esc="cancelEdit"
                                type="text"
                                maxlength="100"
                                class="w-28 px-1 py-0 text-[12px] bg-white/80 dark:bg-gray-900/60 border border-white/50 rounded text-gray-800 dark:text-gray-100 focus:outline-none"
                            />
                            <button type="button" @click="saveEdit(label)" class="hover:opacity-70" title="Guardar">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </button>
                        </template>
                        <template v-else>
                            {{ label.name }}
                            <template v-if="canEdit">
                                <button type="button" @click="startEdit(label)" class="opacity-60 hover:opacity-100" title="Renombrar">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <button type="button" @click="removeLabel(label)" class="opacity-60 hover:opacity-100 hover:text-red-600" title="Eliminar">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </template>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ════ TAB: HEREDAR (preview + selección de etiquetas de otro espacio) ════ -->
            <div v-if="canEdit" v-show="activeTab === 'heredar'" class="space-y-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Elige un espacio, revisa sus etiquetas y marca cuáles copiar. Las que ya existen aquí
                    aparecen como <span class="font-semibold">ya existe</span> y no se duplican.
                </p>

                <template v-if="otherSpaces.length">
                    <!-- 1. Selector de espacio origen (abre un picker animado con buscador) -->
                    <button
                        type="button"
                        @click="openSpacePicker"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-left transition focus:outline-none focus:ring-2 focus:ring-indigo-500/40 hover:border-indigo-300 dark:hover:border-indigo-600"
                    >
                        <span :class="inheritFrom ? 'text-gray-800 dark:text-gray-200 font-medium truncate' : 'text-gray-400'">
                            {{ inheritFromLabel || 'Selecciona un espacio…' }}
                        </span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- 2. Preview + selección -->
                    <div v-if="inheritFrom">
                        <div v-if="loadingSource" class="text-center text-sm text-gray-400 py-6">Cargando etiquetas…</div>

                        <template v-else>
                            <div v-if="!sourceLabels.length" class="text-center text-sm text-gray-400 py-6">
                                Ese espacio no tiene etiquetas.
                            </div>

                            <template v-else>
                                <!-- Barra: seleccionar todas / ninguna + contador -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2 text-[11px] font-medium">
                                        <button
                                            type="button"
                                            @click="selectAllInheritable"
                                            :disabled="!inheritableLabels.length"
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-40 disabled:no-underline"
                                        >Seleccionar todas</button>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <button
                                            type="button"
                                            @click="selectedNames = []"
                                            :disabled="!selectedNames.length"
                                            class="text-gray-500 dark:text-gray-400 hover:underline disabled:opacity-40 disabled:no-underline"
                                        >Ninguna</button>
                                    </div>
                                    <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-full">
                                        {{ selectedNames.length }} de {{ inheritableLabels.length }} se heredarán
                                    </span>
                                </div>

                                <!-- Lista de etiquetas del origen con checkbox -->
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto custom-scrollbar divide-y divide-gray-100 dark:divide-gray-800">
                                    <label
                                        v-for="lbl in sourceLabels"
                                        :key="lbl.id"
                                        :class="[
                                            'flex items-center gap-3 px-3 py-2 transition-colors',
                                            isExisting(lbl.name)
                                                ? 'opacity-60 cursor-not-allowed bg-gray-50/50 dark:bg-gray-800/30'
                                                : 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/60',
                                        ]"
                                    >
                                        <input
                                            type="checkbox"
                                            :disabled="isExisting(lbl.name)"
                                            :checked="selectedNames.includes(lbl.name)"
                                            @change="toggleSelect(lbl.name)"
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 bg-white dark:bg-gray-900 disabled:opacity-50"
                                        />
                                        <span :class="['inline-flex items-center px-2.5 py-1 text-[12px] font-bold uppercase tracking-wider rounded-md border', getLabelColor(lbl.name)]">{{ lbl.name }}</span>
                                        <span v-if="isExisting(lbl.name)" class="ml-auto text-[10px] font-semibold text-gray-400 italic">ya existe</span>
                                    </label>
                                </div>

                                <!-- Heredar seleccionadas -->
                                <button
                                    type="button"
                                    @click="inheritLabels"
                                    :disabled="!selectedNames.length || saving"
                                    class="mt-3 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m-6 3.75l3 3m0 0l3-3m-3 3V1.5" /></svg>
                                    Heredar seleccionadas ({{ selectedNames.length }})
                                </button>
                            </template>
                        </template>
                    </div>
                </template>

                <p v-else class="text-sm text-gray-400 dark:text-gray-500 italic py-2">
                    No hay otros espacios de los que heredar etiquetas.
                </p>
            </div>

            <div class="flex justify-end pt-3 border-t border-gray-100 dark:border-gray-700">
                <button type="button" @click="close" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    Cerrar
                </button>
            </div>
        </div>

        <!-- ── PICKER de espacio (overlay animado, por encima del modal) ──────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showSpacePicker"
                    class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 dark:bg-slate-950/75"
                    @click.self="closeSpacePicker"
                >
                    <Transition
                        appear
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="showSpacePicker"
                            class="w-full max-w-md bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[80vh]"
                        >
                            <!-- Header -->
                            <div class="shrink-0 px-5 py-4 bg-gradient-to-r from-indigo-600 to-cyan-600 text-white flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-bold leading-tight">Elegir espacio</h3>
                                    <p class="text-[11px] text-white/80">Heredar etiquetas desde otro espacio</p>
                                </div>
                                <button
                                    type="button"
                                    @click="closeSpacePicker"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/15 hover:bg-white/25 transition"
                                    title="Cerrar"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <!-- Buscador -->
                            <div class="shrink-0 p-3 border-b border-gray-100 dark:border-gray-800">
                                <div class="relative">
                                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                    </svg>
                                    <input
                                        ref="spaceSearchInput"
                                        v-model="spaceSearch"
                                        type="text"
                                        placeholder="Buscar espacio por nombre o clave…"
                                        class="w-full h-10 pl-9 pr-3 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                                    />
                                </div>
                            </div>

                            <!-- Lista de espacios -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 min-h-0">
                                <button
                                    v-for="s in filteredSpaces"
                                    :key="s.key"
                                    type="button"
                                    @click="pickSpace(s)"
                                    :class="[
                                        'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border text-left transition-colors',
                                        inheritFrom === s.key
                                            ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-700'
                                            : 'border-transparent hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-200 dark:hover:border-gray-700',
                                    ]"
                                >
                                    <span class="w-9 h-9 shrink-0 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 flex items-center justify-center font-bold text-xs">
                                        {{ (s.key || '?').slice(0, 2).toUpperCase() }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">{{ s.name }}</p>
                                        <p class="text-[11px] font-mono text-gray-400">{{ s.key }}</p>
                                    </div>
                                    <svg v-if="inheritFrom === s.key" class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </button>
                                <p v-if="!filteredSpaces.length" class="text-center text-sm text-gray-400 py-8">
                                    Sin espacios que coincidan.
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </ModalView>
</template>

<script setup>
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import { getLabelColor } from '@/Composables/GestionProyectos/useLabelColors';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    projectKey: { type: String, default: '' },
    canEdit: { type: Boolean, default: false },
    spaces: { type: Array, default: () => [] }, // [{key, name}]
});

const emit = defineEmits(['update:show', 'labels-changed', 'error']);

const localLabels = ref([]);
const loading = ref(false);
const saving = ref(false);
const newName = ref('');
const inheritFrom = ref('');
const activeTab = ref('etiquetas'); // 'etiquetas' | 'heredar'
const editingId = ref(null);
const editName = ref('');

// ── Estado del flujo "Heredar" (preview + selección) ──────────────────────────
const sourceLabels = ref([]);     // etiquetas del espacio origen seleccionado
const loadingSource = ref(false);
const selectedNames = ref([]);    // nombres marcados para heredar

const otherSpaces = computed(() => props.spaces.filter((s) => s.key !== props.projectKey));

// ── Picker de espacio (overlay animado con buscador) ──────────────────────────
const showSpacePicker = ref(false);
const spaceSearch = ref('');
const spaceSearchInput = ref(null);
const filteredSpaces = computed(() => {
    const q = spaceSearch.value.trim().toLowerCase();
    if (!q) return otherSpaces.value;
    return otherSpaces.value.filter(
        (s) => (s.name || '').toLowerCase().includes(q) || (s.key || '').toLowerCase().includes(q)
    );
});
const inheritFromLabel = computed(() => {
    const s = otherSpaces.value.find((x) => x.key === inheritFrom.value);
    return s ? `${s.name} (${s.key})` : '';
});
function openSpacePicker() {
    spaceSearch.value = '';
    showSpacePicker.value = true;
    nextTick(() => spaceSearchInput.value?.focus());
}
function closeSpacePicker() {
    showSpacePicker.value = false;
}
function pickSpace(s) {
    inheritFrom.value = s.key; // el watch(inheritFrom) dispara la carga del preview
    closeSpacePicker();
}

// Nombres ya existentes en este espacio (lowercase) → no se duplican.
const existingNamesLower = computed(
    () => new Set(localLabels.value.map((l) => (l.name || '').toLowerCase()))
);
function isExisting(name) {
    return existingNamesLower.value.has((name || '').toLowerCase());
}
// Etiquetas del origen que SÍ se pueden heredar (no existen aún aquí).
const inheritableLabels = computed(
    () => sourceLabels.value.filter((l) => !isExisting(l.name))
);
function toggleSelect(name) {
    const i = selectedNames.value.indexOf(name);
    if (i > -1) selectedNames.value.splice(i, 1);
    else selectedNames.value.push(name);
}
function selectAllInheritable() {
    selectedNames.value = inheritableLabels.value.map((l) => l.name);
}

// Al elegir un espacio origen, traer sus etiquetas (preview) y marcar por defecto
// las que aún no existen aquí.
watch(inheritFrom, async (key) => {
    selectedNames.value = [];
    sourceLabels.value = [];
    if (!key) return;
    loadingSource.value = true;
    try {
        const { data } = await window.axios.get(
            route('gestion-proyectos.projects.labels.index', { projectKey: key })
        );
        sourceLabels.value = data;
        selectedNames.value = inheritableLabels.value.map((l) => l.name);
    } catch {
        emit('error', 'No se pudieron cargar las etiquetas del espacio.');
    } finally {
        loadingSource.value = false;
    }
});

watch(() => props.show, (open) => {
    if (open && props.projectKey) {
        newName.value = '';
        inheritFrom.value = '';
        sourceLabels.value = [];
        selectedNames.value = [];
        showSpacePicker.value = false;
        spaceSearch.value = '';
        activeTab.value = 'etiquetas';
        cancelEdit();
        loadLabels();
    }
});

async function loadLabels() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('gestion-proyectos.projects.labels.index', { projectKey: props.projectKey }));
        localLabels.value = data;
    } catch {
        emit('error', 'No se pudieron cargar las etiquetas.');
    } finally {
        loading.value = false;
    }
}

async function createLabel() {
    const name = newName.value.trim();
    if (!name || saving.value) return;
    saving.value = true;
    try {
        const { data } = await window.axios.post(route('gestion-proyectos.projects.labels.store', { projectKey: props.projectKey }), { name });
        localLabels.value.push(data);
        localLabels.value.sort((a, b) => a.name.localeCompare(b.name));
        newName.value = '';
        emit('labels-changed', localLabels.value);
    } catch (err) {
        emit('error', extractError(err));
    } finally {
        saving.value = false;
    }
}

function startEdit(label) {
    editingId.value = label.id;
    editName.value = label.name;
}
function cancelEdit() {
    editingId.value = null;
    editName.value = '';
}
async function saveEdit(label) {
    const name = editName.value.trim();
    if (!name) return;
    if (name === label.name) { cancelEdit(); return; }
    saving.value = true;
    try {
        const { data } = await window.axios.put(route('gestion-proyectos.labels.update', { id: label.id }), { name });
        const idx = localLabels.value.findIndex((l) => l.id === label.id);
        if (idx >= 0) localLabels.value[idx] = data;
        cancelEdit();
        emit('labels-changed', localLabels.value);
    } catch (err) {
        emit('error', extractError(err));
    } finally {
        saving.value = false;
    }
}

async function removeLabel(label) {
    const ok = window.showConfirm
        ? await window.showConfirm('¿Eliminar etiqueta?', `"${label.name}" se eliminará de este espacio.`, { confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then((r) => r.isConfirmed)
        : window.confirm(`¿Eliminar "${label.name}"?`);
    if (!ok) return;
    try {
        await window.axios.delete(route('gestion-proyectos.labels.destroy', { id: label.id }));
        localLabels.value = localLabels.value.filter((l) => l.id !== label.id);
        emit('labels-changed', localLabels.value);
    } catch (err) {
        emit('error', extractError(err));
    }
}

async function inheritLabels() {
    if (!inheritFrom.value || !selectedNames.value.length || saving.value) return;
    saving.value = true;
    try {
        const { data } = await window.axios.post(
            route('gestion-proyectos.projects.labels.inherit', { projectKey: props.projectKey }),
            { from_project_key: inheritFrom.value, names: selectedNames.value }
        );
        window.showToast?.(data.message ?? 'Etiquetas heredadas.', 'success', { timer: 3000 });
        inheritFrom.value = '';
        sourceLabels.value = [];
        selectedNames.value = [];
        await loadLabels();
        activeTab.value = 'etiquetas'; // mostrar el resultado en la lista
        emit('labels-changed', localLabels.value);
    } catch (err) {
        emit('error', extractError(err));
    } finally {
        saving.value = false;
    }
}

function extractError(err) {
    return err?.response?.data?.error
        || (err?.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' · ') : null)
        || err?.response?.data?.message
        || 'Error al guardar la etiqueta.';
}

function close() { emit('update:show', false); }
</script>
