<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Seguimiento de Cambios"
        size="2xl"
        :show-cancel-button="false"
        :show-confirm-button="false"
        @close="close"
    >
        <template #subtitle>
            Tarea:
            <span class="font-mono font-semibold text-white/90">{{ issueKey }}</span>
        </template>

        <!-- Body: timeline -->
        <div v-if="loading" class="flex items-center justify-center py-10">
            <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
            <span class="ml-2 text-sm text-gray-500">Cargando historial...</span>
        </div>

        <div
            v-else-if="!entries.length"
            class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-zinc-500"
        >
            <svg class="w-10 h-10 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium">Sin registros aún</p>
            <p class="text-xs mt-1">Los cambios en campos críticos aparecerán aquí automáticamente.</p>
        </div>

        <div v-else class="space-y-4">
            <div v-for="entry in entries" :key="entry.id" class="flex gap-3">
                <div class="shrink-0 flex flex-col items-center">
                    <div
                        :class="[
                            'w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold',
                            entry.action === 'created'  ? 'bg-emerald-500' :
                            entry.action === 'deleted'  ? 'bg-red-500' :
                                                         'bg-amber-500',
                        ]"
                    >
                        <!-- created -->
                        <svg v-if="entry.action === 'created'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <!-- deleted -->
                        <svg v-else-if="entry.action === 'deleted'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <!-- updated -->
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div class="w-px flex-1 bg-gray-200 dark:bg-zinc-700 mt-1 min-h-[16px]"></div>
                </div>

                <div class="flex-1 min-w-0 pb-4">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ entry.user_name || 'Sistema' }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-zinc-500">{{ entry.created_at }}</span>
                        <span
                            :class="[
                                'text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded',
                                entry.action === 'created'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : entry.action === 'deleted'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                            ]"
                        >
                            {{ entry.action === 'created' ? 'Creado' : entry.action === 'deleted' ? 'Eliminado' : 'Cambio' }}
                        </span>
                    </div>

                    <!-- Tarea creada: mostrar resumen de campos iniciales -->
                    <template v-if="entry.action === 'created'">
                        <div class="space-y-1">
                            <div
                                v-for="change in entry.changes"
                                :key="change.field"
                                class="flex items-center gap-2 text-xs"
                            >
                                <span class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">{{ change.label }}</span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ change.new }}</span>
                            </div>
                        </div>
                    </template>

                    <!-- Tarea eliminada -->
                    <template v-else-if="entry.action === 'deleted'">
                        <div class="space-y-1">
                            <div
                                v-for="change in entry.changes"
                                :key="change.field"
                                class="flex items-center gap-2 text-xs"
                            >
                                <span class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">{{ change.label }}</span>
                                <span class="text-red-400 line-through">{{ change.old }}</span>
                            </div>
                        </div>
                    </template>

                    <!-- Cambio de campos (updated) -->
                    <template v-else>
                        <div class="space-y-1">
                            <div
                                v-for="change in entry.changes"
                                :key="change.field"
                                class="flex items-center gap-2 text-xs"
                            >
                                <span class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">{{ change.label }}</span>
                                <span class="text-gray-400 line-through">{{ change.old }}</span>
                                <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ change.new }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </ModalView>
</template>

<script setup>
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    issueKey: { type: String, default: null },
});

const emit = defineEmits(['update:show']);

const entries = ref([]);
const loading = ref(false);

watch(
    () => props.show,
    async (val) => {
        if (val && props.issueKey) {
            await loadTimeline();
        } else if (!val) {
            entries.value = [];
        }
    }
);

async function loadTimeline() {
    if (!props.issueKey) return;
    loading.value = true;
    entries.value = [];
    try {
        const { data } = await window.axios.get(
            route('gestion-proyectos.timeline.index', { key: props.issueKey })
        );
        entries.value = data;
    } catch {
        // silencioso
    } finally {
        loading.value = false;
    }
}

function close() {
    emit('update:show', false);
}
</script>
