<script setup>
/**
 * Modal del TÍTULO (summary) de una actividad / subactividad / reprogramación.
 *
 * Réplica del modal de Descripción (clic en la celda → ModalView), pero además:
 *  - Permite EDITAR el título (si canEdit) y lo guarda por el mismo endpoint que la
 *    edición inline (PATCH gestion-proyectos.update con { summary }).
 *  - Muestra el HISTÓRICO de títulos si fue cambiando (del timeline de auditoría,
 *    filtrando los cambios del campo `summary`). Si nunca cambió, no muestra historial.
 */
import { ref, computed, watch } from 'vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    issue: { type: Object, default: null },          // { key, summary, _isReprog, reprogramacion_n }
    projectKey: { type: String, default: '' },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['update:show', 'updated']);

const editTitle = ref('');
const saving = ref(false);
const loadingHist = ref(false);
const historial = ref([]);
const histPage = ref(1);          // página actual del histórico (10 por tanda)
const histHasMore = ref(false);   // ¿hay más títulos para "Cargar más"?
const histTotal = ref(0);         // total de cambios de título

const changed = computed(
    () => props.issue && editTitle.value.trim() !== (props.issue.summary ?? '').trim()
);

function cerrar() {
    emit('update:show', false);
}

// Carga el histórico de títulos (solo cambios de summary), paginado 10 en 10.
// reset=true vuelve a la primera página; reset=false agrega la siguiente tanda.
async function cargarHistorial(reset = true) {
    if (!props.issue?.key) return;
    if (reset) { histPage.value = 1; historial.value = []; }
    loadingHist.value = true;
    try {
        const { data } = await window.axios.get(
            route('gestion-proyectos.timeline.titulos', { key: props.issue.key }),
            { params: { page: histPage.value } }
        );
        const items = data?.items || [];
        historial.value = reset ? items : [...historial.value, ...items];
        histHasMore.value = !!data?.has_more;
        histTotal.value = data?.total ?? historial.value.length;
    } catch {
        // El histórico es informativo: si falla, simplemente no se muestra.
        if (reset) { historial.value = []; histHasMore.value = false; histTotal.value = 0; }
    } finally {
        loadingHist.value = false;
    }
}

function cargarMas() {
    if (loadingHist.value || !histHasMore.value) return;
    histPage.value += 1;
    cargarHistorial(false);
}

async function guardar() {
    if (!props.canEdit || saving.value || !changed.value || !props.issue?.key) return;
    const nuevo = editTitle.value.trim();
    if (!nuevo) {
        window.showToast?.('El título no puede quedar vacío.', 'error');
        return;
    }
    saving.value = true;
    try {
        const { status, data } = await window.axios.patch(
            route('gestion-proyectos.update', { key: props.issue.key }),
            { project_key: props.projectKey, summary: nuevo },
            { validateStatus: () => true }
        );
        if (status >= 200 && status < 300) {
            emit('updated', { key: props.issue.key, summary: nuevo });
            window.showToast?.('Título actualizado.', 'success', { timer: 2000 });
            await cargarHistorial(); // refleja el nuevo cambio en el histórico
        } else {
            const msg = data?.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data?.error || data?.message || 'No se pudo actualizar el título.');
            window.showToast?.(msg, 'error');
        }
    } catch {
        window.showToast?.('Error de conexión al actualizar el título.', 'error');
    } finally {
        saving.value = false;
    }
}

// Al abrir: precargar el título y traer el histórico.
watch(
    () => props.show,
    (abierto) => {
        if (abierto && props.issue) {
            editTitle.value = props.issue.summary ?? '';
            cargarHistorial();
        }
    },
    { immediate: true }
);
</script>

<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Título"
        size="lg"
        :hide-footer="true"
        @close="cerrar"
    >
        <template #subtitle>
            <span class="font-mono bg-white/20 px-1.5 py-0.5 rounded text-[10px]">{{ issue?.key }}</span>
            <span v-if="issue?._isReprog" class="ml-1.5 inline-flex items-center h-4 px-1.5 rounded text-[10px] font-bold bg-amber-500 text-white">Reprogramación R{{ issue?.reprogramacion_n }}</span>
        </template>

        <div class="space-y-5 max-h-[65vh] overflow-y-auto custom-scrollbar pr-1">
            <!-- Título (editable si tiene permiso) -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Título</label>
                <textarea
                    v-model="editTitle"
                    :readonly="!canEdit"
                    maxlength="500"
                    rows="3"
                    placeholder="Título de la actividad"
                    class="w-full text-sm px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 resize-y leading-relaxed focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    :class="{ 'bg-gray-50 dark:bg-gray-800/60 cursor-default': !canEdit }"
                ></textarea>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-[11px] text-gray-400">{{ editTitle.length }}/500</span>
                    <button
                        v-if="canEdit"
                        type="button"
                        @click="guardar"
                        :disabled="saving || !changed"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-[13px] font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
                    >
                        {{ saving ? 'Guardando…' : 'Guardar título' }}
                    </button>
                </div>
            </div>

            <!-- Histórico de títulos: solo si el título ha cambiado alguna vez -->
            <div v-if="historial.length" class="border-t border-gray-100 dark:border-gray-800 pt-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                    Histórico de títulos ({{ histTotal }})
                </p>
                <ul class="space-y-2.5">
                    <li
                        v-for="(h, i) in historial"
                        :key="i"
                        class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 px-3 py-2"
                    >
                        <div class="text-[13px] text-gray-700 dark:text-gray-200 leading-snug">
                            <span class="line-through text-gray-400 dark:text-gray-500">{{ h.old }}</span>
                            <span class="mx-1 text-indigo-500">→</span>
                            <span class="font-medium">{{ h.new }}</span>
                        </div>
                        <div class="mt-1 text-[11px] text-gray-400">{{ h.user }} · {{ h.date }}</div>
                    </li>
                </ul>
                <div v-if="histHasMore" class="mt-3 text-center">
                    <button
                        type="button"
                        @click="cargarMas"
                        :disabled="loadingHist"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-[13px] font-semibold text-indigo-600 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/40 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 disabled:opacity-50 transition-colors"
                    >
                        {{ loadingHist ? 'Cargando…' : `Cargar más (${historial.length}/${histTotal})` }}
                    </button>
                </div>
            </div>
            <p v-else-if="!loadingHist" class="text-[12px] text-gray-400 italic">
                El título no ha cambiado desde su creación.
            </p>
        </div>
    </ModalView>
</template>
