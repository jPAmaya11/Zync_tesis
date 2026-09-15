<template>
    <div
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 py-3 border-t border-gray-100 dark:border-gray-700"
    >
        <div class="text-xs text-gray-500 dark:text-gray-400 space-x-1">
            <span>Mostrando <strong>{{ filteredCount }}</strong></span>
            <span v-if="totalLoaded !== filteredCount">
                de <strong>{{ totalLoaded }}</strong> cargados
            </span>
            <span v-if="totalCount">
                · <strong>{{ totalCount }}</strong> en total
            </span>
        </div>

        <div class="flex items-center gap-3">
            <button
                v-if="!isLast"
                @click="$emit('load-more')"
                :disabled="loading"
                class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-lg border border-indigo-300 dark:border-indigo-600 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 disabled:opacity-60 transition"
            >
                <svg
                    v-if="loading"
                    class="animate-spin w-3.5 h-3.5"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ loading ? 'Cargando...' : 'Cargar más' }}
            </button>
            <span
                v-else-if="totalLoaded > 0"
                class="text-[11px] text-gray-400 dark:text-gray-500"
            >
                Todos los issues cargados
            </span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    /** Issues actualmente filtrados/mostrados. */
    filteredCount: { type: Number, default: 0 },
    /** Total de issues cargados (puede ser > filtered si hay filtros locales). */
    totalLoaded: { type: Number, default: 0 },
    /** Total en backend (si lo conocemos). */
    totalCount: { type: Number, default: 0 },
    /** Si ya cargamos la última página. */
    isLast: { type: Boolean, default: false },
    /** Spinner mientras se carga la siguiente página. */
    loading: { type: Boolean, default: false },
});

defineEmits(['load-more']);
</script>
