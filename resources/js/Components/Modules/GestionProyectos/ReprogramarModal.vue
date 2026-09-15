<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="$emit('close')" />

                <!-- Modal -->
                <div class="gp-modal relative w-full max-w-xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 flex flex-col max-h-[92vh]">

                    <!-- Header -->
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20 rounded-t-2xl shrink-0">
                        <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-800/40 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Reprogramar actividad</p>
                            <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ issueKey }} → <span class="text-amber-600 dark:text-amber-400">{{ issueKey }}-R{{ nextN }}</span></p>
                        </div>
                        <button @click="$emit('close')" class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Motivo (persistente, evidencia obligatoria) -->
                    <div class="px-6 pt-4 shrink-0">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Motivo de reprogramación <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="form.motivo"
                            rows="2"
                            placeholder="Por qué se reprograma (mín. 10 caracteres)…"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50 resize-none"
                            :class="{ 'border-red-400 dark:border-red-600': errors.motivo }"
                        />
                        <p v-if="errors.motivo" class="mt-1 text-[10px] text-red-500">{{ errors.motivo }}</p>
                    </div>

                    <!-- Tabs -->
                    <div class="px-6 mt-3 border-b border-gray-200 dark:border-gray-700 shrink-0">
                        <div class="flex gap-1">
                            <button
                                v-for="t in tabs"
                                :key="t.id"
                                type="button"
                                @click="activeTab = t.id"
                                :class="[
                                    'px-3 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors',
                                    activeTab === t.id
                                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200',
                                ]"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Body (tab content) -->
                    <div class="flex-1 overflow-y-auto px-6 py-5 min-h-[260px]">

                        <!-- ── Nombres / Datos ── -->
                        <div v-show="activeTab === 'datos'" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.summary"
                                    type="text"
                                    placeholder="Nombre del nuevo plan…"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50"
                                    :class="{ 'border-red-400 dark:border-red-600': errors.summary }"
                                />
                                <p v-if="errors.summary" class="mt-1 text-[10px] text-red-500">{{ errors.summary }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                                <textarea
                                    v-model="form.description"
                                    rows="8"
                                    placeholder="Describe el alcance del nuevo plan…"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50 resize-none min-h-[180px]"
                                />
                            </div>

                            <!-- Estado: solo Pendiente / En Revisión -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Estado inicial</label>
                                <div class="relative">
                                    <button type="button" @click="showStatusDropdown = !showStatusDropdown"
                                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50">
                                        <span>{{ form.status }}</span>
                                        <svg class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                    <div v-if="showStatusDropdown" class="fixed inset-0 z-40" @click="showStatusDropdown = false"></div>
                                    <div v-if="showStatusDropdown" class="absolute z-50 w-full bottom-full mb-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1">
                                        <button v-for="s in STATUS_OPTIONS" :key="s" type="button"
                                            @click="form.status = s; showStatusDropdown = false"
                                            class="w-full px-3 py-2 text-sm text-left hover:bg-amber-50 dark:hover:bg-amber-500/10 text-gray-700 dark:text-gray-200">
                                            {{ s }}
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-1 text-[10px] text-gray-400">El plan sucesor arranca en Pendiente.</p>
                            </div>
                        </div>

                        <!-- ── Alcance ── -->
                        <div v-show="activeTab === 'alcance'" class="space-y-4">
                            <!-- Prioridad -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Prioridad</label>
                                <div class="relative">
                                    <button type="button" @click="showPriorityDropdown = !showPriorityDropdown; showAssigneeDropdown = false"
                                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <svg v-if="form.priority" class="w-3.5 h-3.5 shrink-0" :class="severityIcon(form.priority).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(form.priority).path" /></svg>
                                            <span :class="form.priority ? '' : 'text-gray-400'">{{ form.priority || 'Seleccionar prioridad…' }}</span>
                                        </span>
                                        <svg class="w-4 h-4 opacity-60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                    <div v-if="showPriorityDropdown" class="fixed inset-0 z-40" @click="showPriorityDropdown = false"></div>
                                    <div v-if="showPriorityDropdown" class="absolute z-50 w-full top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1">
                                        <button type="button" @click="form.priority = ''; showPriorityDropdown = false"
                                            class="w-full px-3 py-2 text-sm text-left text-gray-400 italic hover:bg-gray-50 dark:hover:bg-gray-700">Sin prioridad</button>
                                        <button v-for="p in priorityOptions" :key="p" type="button"
                                            @click="form.priority = p; showPriorityDropdown = false"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-amber-50 dark:hover:bg-amber-500/10 text-gray-700 dark:text-gray-200">
                                            <svg class="w-3.5 h-3.5 shrink-0" :class="severityIcon(p).cls" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" :d="severityIcon(p).path" /></svg>
                                            {{ p }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Persona Asignada -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Persona Asignada</label>
                                <div class="relative">
                                    <button type="button" @click="showAssigneeDropdown = !showAssigneeDropdown; showPriorityDropdown = false"
                                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <template v-if="selectedAssigneeUser">
                                                <img v-if="selectedAssigneeUser.avatar_url" :src="selectedAssigneeUser.avatar_url" class="w-5 h-5 rounded-full object-cover shrink-0" />
                                                <span v-else class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-bold inline-flex items-center justify-center shrink-0">{{ initials(selectedAssigneeUser.display_name) }}</span>
                                                <span class="truncate">{{ selectedAssigneeUser.display_name }}</span>
                                            </template>
                                            <span v-else class="text-gray-400">Sin asignar</span>
                                        </span>
                                        <svg class="w-4 h-4 opacity-60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                    <div v-if="showAssigneeDropdown" class="fixed inset-0 z-40" @click="showAssigneeDropdown = false; clearUserSearch()"></div>
                                    <div v-if="showAssigneeDropdown" class="absolute z-50 w-full top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 max-h-56 overflow-y-auto">
                                        <!-- Buscador server-side: alcanza a cualquier miembro del espacio -->
                                        <div class="px-2 pt-1 pb-1.5 sticky top-0 bg-white dark:bg-gray-800">
                                            <input
                                                :value="userQuery"
                                                @input="searchUsers(projectKey, $event.target.value)"
                                                @click.stop
                                                type="text"
                                                placeholder="Buscar por nombre o email…"
                                                class="w-full px-2.5 py-1.5 text-xs rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50"
                                            />
                                        </div>
                                        <button type="button" @click="form.assignee_id = ''; showAssigneeDropdown = false; clearUserSearch()"
                                            class="w-full px-3 py-2 text-sm text-left text-gray-400 italic hover:bg-gray-50 dark:hover:bg-gray-700">Sin asignar</button>
                                        <div v-if="usersLoading" class="px-3 py-2 text-xs text-gray-400">Buscando…</div>
                                        <button v-for="u in displayUsers" :key="u.account_id" type="button"
                                            @click="pickAssignee(u)"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-amber-50 dark:hover:bg-amber-500/10 text-gray-700 dark:text-gray-200">
                                            <img v-if="u.avatar_url" :src="u.avatar_url" class="w-6 h-6 rounded-full object-cover shrink-0" />
                                            <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold inline-flex items-center justify-center shrink-0">{{ initials(u.display_name) }}</span>
                                            <span class="truncate">{{ u.display_name }}</span>
                                        </button>
                                        <div v-if="!usersLoading && userQuery.trim() && displayUsers.length === 0" class="px-3 py-2 text-xs text-gray-400">Sin resultados.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Fechas ── -->
                        <div v-show="activeTab === 'fechas'" class="space-y-4">
                            <!-- Desviación -->
                           

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Inicio <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.nueva_inicio"
                                        type="date"
                                        :min="bloquearFechas ? (startDateOriginal || undefined) : undefined"
                                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50 cursor-pointer"
                                        :class="{ 'border-red-400 dark:border-red-600': errors.nueva_inicio }"
                                    />
                                    <p v-if="bloquearFechas && startDateOriginal" class="mt-1 text-[10px] text-gray-400">No anterior al inicio del padre ({{ startDateOriginal }}).</p>
                                    <p v-else-if="!bloquearFechas" class="mt-1 text-[10px] text-emerald-500/80">Fecha de inicio libre (cualquier fecha).</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Fecha Límite <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.nueva_limite"
                                        type="date"
                                        :min="form.nueva_inicio || undefined"
                                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50 cursor-pointer"
                                        :class="{ 'border-red-400 dark:border-red-600': errors.nueva_limite }"
                                    />
                                </div>
                            </div>

                            <div v-if="form.nueva_inicio && form.nueva_limite">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Días Estimados</label>
                                <input
                                    v-model.number="form.dias_estimados"
                                    type="number"
                                    min="0"
                                    max="9999"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-400/50"
                                />
                                <p class="mt-1 text-[10px] text-gray-400">Calculado desde las fechas; puedes ajustarlo.</p>
                            </div>
                        </div>

                        <!-- Error general -->
                        <div v-if="errorGeneral" class="mt-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700">
                            <p class="text-xs text-red-600 dark:text-red-400">{{ errorGeneral }}</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="shrink-0 border-t border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/60 rounded-b-2xl flex items-center justify-end gap-3">
                        <button @click="$emit('close')" :disabled="saving"
                            class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors">
                            Cancelar
                        </button>
                        <button @click="submit" :disabled="!canSubmit || saving"
                            class="px-5 py-2 text-sm font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2">
                            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ saving ? 'Reprogramando…' : `Confirmar R${nextN}` }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { severityIcon } from '@/Composables/GestionProyectos/useSeverityIcon';
import { useAssignableUserSearch } from '@/Composables/GestionProyectos/useAssignableUserSearch';

const props = defineProps({
    show:                 { type: Boolean, default: false },
    issueKey:             { type: String,  default: null },
    fechaLimiteOriginal:  { type: String,  default: null },
    startDateOriginal:    { type: String,  default: null },
    reprogramacionesCount:{ type: Number,  default: 0 },
    availableUsers:       { type: Array,   default: () => [] },
    priorityOptions:      { type: Array,   default: () => [] },
    projectKey:           { type: String,  default: '' },
    // Switch por-espacio "Bloquear fechas anteriores": true ⇒ piso del inicio del padre;
    // false ⇒ la fecha de inicio de la reprogramación queda libre.
    bloquearFechas:       { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'reprogramado']);

const STATUS_OPTIONS = ['Pendiente', 'En Revisión'];
const tabs = [
    { id: 'datos',   label: 'Nombres / Datos' },
    { id: 'alcance', label: 'Alcance' },
    { id: 'fechas',  label: 'Fechas' },
];

const activeTab = ref('datos');
const showStatusDropdown = ref(false);
const showPriorityDropdown = ref(false);
const showAssigneeDropdown = ref(false);

// Búsqueda server-side de usuarios (alcanza a cualquier miembro, no solo los 200 iniciales).
const { query: userQuery, results: userResults, loading: usersLoading, search: searchUsers, clear: clearUserSearch } = useAssignableUserSearch();
const pickedAssignee = ref(null);
const displayUsers = computed(() => (userQuery.value.trim() ? userResults.value : props.availableUsers));

function pickAssignee(u) {
    form.value.assignee_id = u.account_id;
    pickedAssignee.value = u;
    showAssigneeDropdown.value = false;
    clearUserSearch();
}

function blankForm() {
    return {
        motivo: '', summary: '', description: '', status: 'Pendiente',
        priority: '', assignee_id: '', nueva_inicio: '', nueva_limite: '', dias_estimados: null,
    };
}
const form = ref(blankForm());
const errors = ref({});
const errorGeneral = ref('');
const saving = ref(false);

const nextN = computed(() => props.reprogramacionesCount + 1);

const selectedAssigneeUser = computed(() => {
    if (!form.value.assignee_id) return null;
    const id = String(form.value.assignee_id);
    // Buscar en la lista inicial y en los resultados de búsqueda; si la búsqueda lo filtró,
    // usar el usuario cacheado al elegirlo (así el seleccionado siempre se muestra bien).
    return [...props.availableUsers, ...userResults.value].find(u => String(u.account_id) === id)
        ?? (pickedAssignee.value && String(pickedAssignee.value.account_id) === id ? pickedAssignee.value : null);
});

function initials(name) {
    return (name || '?').split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();
}

// ── Fechas: helpers locales sin desfase de timezone ──────────────────────────
function diffDaysLocal(a, b) {
    if (!a || !b) return null;
    const [ay, am, ad] = a.split('-').map(Number);
    const [by, bm, bd] = b.split('-').map(Number);
    return Math.round((new Date(by, bm - 1, bd) - new Date(ay, am - 1, ad)) / 86400000);
}
function addDaysLocal(s, n) {
    if (!s || !Number.isInteger(n)) return '';
    const [y, m, d] = s.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() + n);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}
function fmtDateLocal(s) {
    if (!s) return '—';
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' });
}

const desviacionDias = computed(() => diffDaysLocal(props.fechaLimiteOriginal, form.value.nueva_inicio));
const fechaLimiteOriginalFmt = computed(() => fmtDateLocal(props.fechaLimiteOriginal));

// Sincronización bidireccional fechas ↔ días estimados (guard anti-bucle).
let syncing = false;
watch(() => form.value.nueva_inicio, (val) => {
    if (syncing || !val) return;
    syncing = true;
    const n = parseInt(form.value.dias_estimados);
    if (Number.isInteger(n) && n >= 0) form.value.nueva_limite = addDaysLocal(val, n);
    else if (form.value.nueva_limite) {
        const diff = diffDaysLocal(val, form.value.nueva_limite);
        if (diff !== null && diff >= 0) form.value.dias_estimados = diff;
    }
    nextTick(() => { syncing = false; });
});
watch(() => form.value.nueva_limite, (val) => {
    if (syncing || !val) return;
    syncing = true;
    const diff = diffDaysLocal(form.value.nueva_inicio, val);
    if (diff !== null && diff >= 0) form.value.dias_estimados = diff;
    nextTick(() => { syncing = false; });
});
watch(() => form.value.dias_estimados, (val) => {
    if (syncing) return;
    const n = parseInt(val);
    if (!Number.isInteger(n) || n < 0 || !form.value.nueva_inicio) return;
    syncing = true;
    form.value.nueva_limite = addDaysLocal(form.value.nueva_inicio, n);
    nextTick(() => { syncing = false; });
});

// Reset al abrir
watch(() => props.show, (val) => {
    if (val) {
        form.value = blankForm();
        errors.value = {};
        errorGeneral.value = '';
        activeTab.value = 'datos';
        showStatusDropdown.value = showPriorityDropdown.value = showAssigneeDropdown.value = false;
        pickedAssignee.value = null;
        clearUserSearch();
    }
});

const canSubmit = computed(() => {
    if (saving.value) return false;
    if (form.value.motivo.trim().length < 10) return false;
    if (!form.value.summary.trim()) return false;
    if (!form.value.nueva_inicio || !form.value.nueva_limite) return false;
    if (form.value.nueva_limite < form.value.nueva_inicio) return false;
    // Piso del inicio del padre: solo si el espacio tiene "Bloquear fechas anteriores" activo.
    if (props.bloquearFechas && props.startDateOriginal && form.value.nueva_inicio < props.startDateOriginal) return false;
    return true;
});

async function submit() {
    if (!canSubmit.value) return;
    errors.value = {};
    errorGeneral.value = '';
    saving.value = true;
    try {
        const { status, data } = await axios.post(
            route('gestion-proyectos.reprogramar', { key: props.issueKey }),
            {
                motivo:              form.value.motivo.trim(),
                summary:             form.value.summary.trim(),
                description:         form.value.description?.trim() || null,
                status:              form.value.status,
                priority:            form.value.priority || null,
                assignee_account_id: form.value.assignee_id || null,
                nueva_inicio:        form.value.nueva_inicio,
                nueva_limite:        form.value.nueva_limite,
                dias_estimados:      form.value.dias_estimados,
            },
            { validateStatus: () => true }
        );

        if (status >= 200 && status < 300) {
            emit('reprogramado', { issueKey: props.issueKey, sucesor: data.sucesor });
            emit('close');
            window.showToast?.(data.message || 'Actividad reprogramada.', 'success', { timer: 4000 });
        } else if (status === 422 && data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
            );
            // Asegurar que el usuario vea el primer error (saltar a la pestaña correspondiente).
            if (errors.value.summary) activeTab.value = 'datos';
            else if (errors.value.nueva_inicio || errors.value.nueva_limite) activeTab.value = 'fechas';
        } else {
            errorGeneral.value = data?.error || data?.message || 'Error al reprogramar.';
        }
    } catch {
        errorGeneral.value = 'Error de conexión. Intenta de nuevo.';
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
