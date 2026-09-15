<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Eliminar issues"
        size="sm"
        @close="close"
    >
        <div class="text-center">
            <div
                class="w-14 h-14 rounded-full bg-red-100 dark:bg-red-500/20 flex items-center justify-center mx-auto mb-4 ring-4 ring-red-50 dark:ring-red-500/10"
            >
                <svg
                    class="w-7 h-7 text-red-600 dark:text-red-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                ¿Eliminar {{ selectedKeys.length }} issue(s)?
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                Esta acción eliminará permanentemente los issues seleccionados. Esta acción
                <strong class="text-red-600">no se puede deshacer</strong>.
            </p>
            <p
                class="mt-3 text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2 text-left max-h-20 overflow-y-auto"
            >
                {{ selectedKeys.join(', ') }}
            </p>

            <!-- Resultado de la operación -->
            <div
                v-if="showResult"
                class="mt-3 rounded-lg border p-3 space-y-1 text-left"
                :class="
                    errors.length
                        ? 'border-red-200 bg-red-50 dark:bg-red-500/10 dark:border-red-700/50'
                        : 'border-green-200 bg-green-50 dark:bg-green-500/10 dark:border-green-700/50'
                "
            >
                <p
                    v-if="succeeded.length"
                    class="text-xs text-green-700 dark:text-green-400"
                >
                    ✓ Eliminados: {{ succeeded.join(', ') }}
                </p>
                <div
                    v-if="errors.length"
                    class="text-xs text-red-700 dark:text-red-400"
                >
                    <p class="font-semibold">Fallaron:</p>
                    <p v-for="e in errors" :key="e.key">
                        {{ e.key }}: {{ e.error }}
                    </p>
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
                color="red"
                size="md"
                rounded="xl"
                :loading="loading"
                :disabled="loading"
                @click="onConfirm"
            >
                {{ loading ? 'Eliminando...' : 'Eliminar definitivamente' }}
            </PrimaryButton>
        </template>
    </ModalView>
</template>

<script setup>
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    selectedKeys: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:show', 'completed', 'error']);

const loading = ref(false);
const succeeded = ref([]);
const errors = ref([]);
const showResult = ref(false);

// Resetear estado al abrir
watch(() => props.show, (val) => {
    if (val) {
        succeeded.value = [];
        errors.value = [];
        showResult.value = false;
    }
});

function close() {
    emit('update:show', false);
}

async function onConfirm() {
    loading.value = true;
    succeeded.value = [];
    errors.value = [];
    showResult.value = false;

    try {
        // axios envía la cookie XSRF-TOKEN (siempre fresca) → evita el 419 del <meta> estancado.
        const { data } = await window.axios.post(
            route('gestion-proyectos.bulk.delete'),
            { keys: props.selectedKeys }
        );
        succeeded.value = data.succeeded ?? [];
        errors.value = data.failed ?? [];
        showResult.value = true;

        emit('completed', {
            succeeded: succeeded.value,
            failed: errors.value,
        });

        if (succeeded.value.length > 0) {
            close();
        }
    } catch (e) {
        console.error('[BulkDeleteModal] Error de red', e);
        emit('error', 'Error de conexión al eliminar. Intenta de nuevo.');
        showResult.value = true;
    } finally {
        loading.value = false;
    }
}
</script>
