<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Edición masiva"
        size="md"
        min-height="max-h-[65vh]"
        @close="close"
    >
        <p class="-mt-2 mb-4 text-xs text-gray-500 dark:text-gray-400">
            <strong class="text-gray-900 dark:text-white">{{ selectedKeys.length }}</strong>
            issue(s) seleccionados
        </p>
        <div class="space-y-5">
            <!-- ── Prioridad ── -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >Prioridad</label
                    >
                    <div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 select-none">
                        <span>Conservar como está</span>
                        <Switchtoggle v-model="keepPriority" size="sm" />
                    </div>
                </div>
                <div class="relative" :class="keepPriority ? 'opacity-40 pointer-events-none' : ''">
                    <button
                        type="button"
                        @click="showPriorityDropdown = !showPriorityDropdown"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    >
                        <div class="flex items-center gap-2">
                            <img
                                v-if="priorityIcon"
                                :src="priorityIcon"
                                class="w-4 h-4 shrink-0"
                            />
                            <span v-if="priority" class="font-medium">{{ priority }}</span>
                            <span v-else class="text-gray-400">Sin cambiar</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <!-- Click-away -->
                    <div
                        v-if="showPriorityDropdown"
                        class="fixed inset-0 z-40"
                        @click="showPriorityDropdown = false"
                    ></div>
                    <!-- Dropdown -->
                    <div
                        v-if="showPriorityDropdown"
                        class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-1 max-h-48 overflow-y-auto custom-scrollbar"
                    >
                        <button
                            type="button"
                            @click="priority = ''; showPriorityDropdown = false;"
                            class="w-full px-3 py-2 text-sm text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 text-left italic transition-colors"
                        >
                            Sin cambiar
                        </button>
                        <template v-if="priorities.length">
                            <button
                                v-for="p in priorities"
                                :key="p.id || p.name"
                                type="button"
                                @click="priority = p.name; showPriorityDropdown = false;"
                                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-gray-700 dark:text-gray-200 text-left font-medium transition-colors"
                            >
                                <img v-if="p.iconUrl" :src="p.iconUrl" class="w-4 h-4 shrink-0" />
                                {{ p.name }}
                                <svg
                                    v-if="priority === p.name"
                                    class="ml-auto w-3.5 h-3.5 text-indigo-500"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </template>
                        <template v-else>
                            <button
                                v-for="p in fallbackPriorities"
                                :key="p"
                                type="button"
                                @click="priority = p; showPriorityDropdown = false;"
                                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-gray-700 dark:text-gray-200 text-left font-medium transition-colors"
                            >
                                <img
                                    v-if="findFallbackIcon(p)"
                                    :src="findFallbackIcon(p)"
                                    class="w-4 h-4 shrink-0"
                                />
                                {{ p }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── Persona Asignada ── -->
            <div class="relative">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >Persona asignada</label
                    >
                    <div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 select-none">
                        <span>Conservar como está</span>
                        <Switchtoggle v-model="keepAssignee" size="sm" />
                    </div>
                </div>
                <div :class="keepAssignee ? 'opacity-40 pointer-events-none' : ''">
                    <div class="relative">
                        <input
                            type="text"
                            :value="assigneeDisplay"
                            @input="onAssigneeInput"
                            @focus="onAssigneeFocus"
                            placeholder="Buscar por nombre o email..."
                            autocomplete="off"
                            class="w-full px-3 py-2 pr-8 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        />
                        <svg
                            v-if="assigneeLoading"
                            class="animate-spin absolute right-2 top-2.5 w-4 h-4 text-gray-400"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    </div>
                    <!-- Click-away -->
                    <div
                        v-if="assigneeSuggestions.length"
                        class="fixed inset-0 z-10"
                        @click="assigneeSuggestions = []"
                    ></div>
                    <!-- Dropdown -->
                    <ul
                        v-if="assigneeSuggestions.length"
                        class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg overflow-hidden"
                    >
                        <li
                            v-for="u in assigneeSuggestions"
                            :key="u.account_id"
                            @click="selectAssignee(u)"
                            class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer"
                        >
                            <UserAvatar :src="u.avatar_url" :name="u.display_name" size="md" />
                            <span>{{ u.display_name }}</span>
                            <span v-if="u.email" class="text-xs text-gray-400 ml-auto">{{ u.email }}</span>
                        </li>
                    </ul>
                    <p
                        v-if="assigneeId"
                        class="mt-1 text-[11px] text-green-600 dark:text-green-400"
                    >
                        ✓ Asignado seleccionado
                    </p>
                </div>
            </div>

            <!-- ── Fecha Límite ── -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >Fecha Límite</label
                    >
                    <div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 select-none">
                        <span>Conservar como está</span>
                        <Switchtoggle v-model="keepDueDate" size="sm" />
                    </div>
                </div>
                <input
                    type="date"
                    v-model="dueDate"
                    :disabled="keepDueDate"
                    :class="[
                        'w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-opacity cursor-pointer',
                        keepDueDate ? 'opacity-40 cursor-not-allowed' : '',
                    ]"
                />
                <p class="mt-1 text-[10px] text-gray-400">Recalcula automáticamente los Días Estimados de cada tarea.</p>
            </div>

            <!-- Resultado de la operación -->
            <div
                v-if="showResult"
                class="rounded-lg border p-3 space-y-1"
                :class="
                    errors.length
                        ? 'border-red-200 bg-red-50 dark:bg-red-500/10 dark:border-red-700/50'
                        : 'border-green-200 bg-green-50 dark:bg-green-500/10 dark:border-green-700/50'
                "
            >
                <p v-if="succeeded.length" class="text-xs text-green-700 dark:text-green-400">
                    ✓ Actualizados: {{ succeeded.join(', ') }}
                </p>
                <div v-if="errors.length" class="text-xs text-red-700 dark:text-red-400">
                    <p class="font-semibold mb-1">Fallaron:</p>
                    <p v-for="e in errors" :key="e.key">{{ e.key }}: {{ e.error }}</p>
                </div>
            </div>
        </div>

        <template #footer>
            <PrimaryButton
                variant="soft"
                color="gray"
                size="md"
                rounded="xl"
                @click="close"
            >
                Cancelar
            </PrimaryButton>
            <PrimaryButton
                variant="solid"
                color="indigo"
                size="md"
                rounded="xl"
                :loading="loading"
                :disabled="loading || !hasChanges"
                @click="onSubmit"
            >
                {{ loading ? 'Aplicando...' : 'Aplicar cambios' }}
            </PrimaryButton>
        </template>
    </ModalView>
</template>

<script setup>
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import Switchtoggle from '@/Components/Inputs/Switchtoggle.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** Issue keys seleccionadas para editar masivamente. */
    selectedKeys: { type: Array, default: () => [] },
    /** Catálogo oficial de Jira priorities (con iconUrl). */
    priorities: { type: Array, default: () => [] },
    /** Fallback simple — strings de prioridades cuando Jira no responde. */
    fallbackPriorities: { type: Array, default: () => [] },
    /** Para resolver iconos en el fallback (objetos con name + icon_url). */
    uniquePriorities: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:show', 'completed', 'error']);

// ── Form state ───────────────────────────────────────────────────────────────
const priority = ref('');
const showPriorityDropdown = ref(false);

const assigneeId = ref('');
const assigneeDisplay = ref('');
const assigneeSuggestions = ref([]);
const assigneeLoading = ref(false);
let assigneeTimer = null;

const dueDate = ref('');

// Keep flags (true = no actualizar este campo)
const keepPriority = ref(true);
const keepAssignee = ref(true);
const keepDueDate = ref(true);

// Resultado de la operación
const loading = ref(false);
const succeeded = ref([]);
const errors = ref([]);
const showResult = ref(false);

// ── Computeds ────────────────────────────────────────────────────────────────
const priorityIcon = computed(() => {
    if (!priority.value) return null;
    return (
        props.priorities.find((p) => p.name === priority.value)?.iconUrl ||
        props.uniquePriorities.find((p) => p.name === priority.value)?.icon_url ||
        null
    );
});

const hasChanges = computed(() => {
    if (!keepPriority.value && priority.value) return true;
    if (!keepAssignee.value && assigneeId.value) return true;
    if (!keepDueDate.value && dueDate.value) return true;
    return false;
});

// ── Lifecycle ────────────────────────────────────────────────────────────────
watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
    if (val) resetForm();
});

function resetForm() {
    priority.value = '';
    showPriorityDropdown.value = false;
    assigneeId.value = '';
    assigneeDisplay.value = '';
    assigneeSuggestions.value = [];
    dueDate.value = '';
    keepPriority.value = true;
    keepAssignee.value = true;
    keepDueDate.value = true;
    succeeded.value = [];
    errors.value = [];
    showResult.value = false;
}

function close() {
    emit('update:show', false);
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function findFallbackIcon(name) {
    return props.uniquePriorities.find((up) => up.name === name)?.icon_url ?? null;
}

// ── Assignee autocomplete ────────────────────────────────────────────────────
function onAssigneeInput(e) {
    const q = e.target.value;
    assigneeDisplay.value = q;
    assigneeId.value = '';
    if (q.length < 2) {
        assigneeSuggestions.value = [];
        return;
    }
    clearTimeout(assigneeTimer);
    assigneeTimer = setTimeout(async () => {
        assigneeLoading.value = true;
        try {
            const resp = await axios.get(route('gestion-proyectos.users'), { params: { q } });
            assigneeSuggestions.value = resp.data ?? [];
        } catch {}
        assigneeLoading.value = false;
    }, 300);
}

function onAssigneeFocus() {
    if (assigneeSuggestions.value.length > 0) return;
    // Pre-cargar lista vacía hasta que el usuario escriba — el padre puede
    // pasar usuarios pre-cargados via prop si se quiere optimizar.
}

function selectAssignee(user) {
    assigneeId.value = user.account_id;
    assigneeDisplay.value = user.display_name;
    assigneeSuggestions.value = [];
}

// ── Submit ───────────────────────────────────────────────────────────────────
async function onSubmit() {
    if (!hasChanges.value) return;

    const payload = { keys: props.selectedKeys };
    if (!keepPriority.value && priority.value) payload.priority = priority.value;
    if (!keepAssignee.value && assigneeId.value)
        payload.assignee_account_id = assigneeId.value;
    if (!keepDueDate.value && dueDate.value) payload.fecha_limite = dueDate.value;

    loading.value = true;
    succeeded.value = [];
    errors.value = [];
    showResult.value = false;

    try {
        const resp = await axios.post(
            route('gestion-proyectos.bulk.update'),
            payload,
            { validateStatus: () => true }
        );
        const data = resp.data;
        succeeded.value = data.succeeded ?? [];
        errors.value = data.failed ?? [];
        showResult.value = true;

        emit('completed', { succeeded: succeeded.value, failed: errors.value });

        if (succeeded.value.length > 0) {
            close();
        }
    } catch (e) {
        console.error('[BulkEditModal] Error de red', e);
        emit('error', 'Error de conexión al actualizar. Intenta de nuevo.');
        showResult.value = true;
    } finally {
        loading.value = false;
    }
}
</script>
