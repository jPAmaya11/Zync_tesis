<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-10 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-10 opacity-0"
        >
            <div
                v-if="count > 0"
                class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 flex items-center gap-1 h-12 pl-2 pr-1.5 rounded-2xl ring-1 ring-black/5 dark:ring-white/10 shadow-2xl backdrop-blur-md bg-white/95 dark:bg-gray-900/95"
            >
                <!-- Contador -->
                <div class="flex items-center gap-2 pl-2 pr-3 py-1.5 mr-1">
                    <span
                        class="flex items-center justify-center min-w-[24px] h-6 px-2 text-[11px] font-bold rounded-md bg-indigo-500 text-white shadow-sm"
                    >
                        {{ count }}
                    </span>
                    <span
                        class="text-[12px] font-medium text-gray-600 dark:text-gray-300"
                    >
                        seleccionados
                    </span>
                </div>

                <!-- Divisor -->
                <div class="w-px h-7 bg-gray-200 dark:bg-gray-700"></div>

                <!-- Grupo de Acciones -->
                <div class="flex items-center gap-0.5">
                    <button
                        v-if="canEdit"
                        @click="$emit('edit')"
                        :disabled="loading"
                        class="group flex items-center gap-2 px-3 h-9 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 text-gray-700 dark:text-gray-200 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-300"
                    >
                        <svg
                            class="w-4 h-4 text-gray-400 group-hover:text-indigo-500 dark:text-gray-500 dark:group-hover:text-indigo-400 transition-colors"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                            />
                        </svg>
                        Editar campos
                    </button>

                    <button
                        v-if="canDelete"
                        @click="$emit('delete')"
                        :disabled="loading"
                        class="group flex items-center gap-2 px-3 h-9 text-sm font-medium rounded-lg transition-colors disabled:opacity-50 text-gray-700 dark:text-gray-200 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <svg
                            class="w-4 h-4 text-gray-400 group-hover:text-red-500 dark:text-gray-500 dark:group-hover:text-red-400 transition-colors"
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
                        Eliminar
                    </button>
                </div>

                <!-- Divisor -->
                <div class="w-px h-7 bg-gray-200 dark:bg-gray-700 ml-0.5"></div>

                <!-- Cerrar -->
                <button
                    @click="$emit('clear')"
                    title="Deseleccionar todo (Esc)"
                    class="ml-0.5 w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
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
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    /** Cuántos items están seleccionados (0 = oculto). */
    count: { type: Number, default: 0 },
    /** Mostrar botón "Editar campos". */
    canEdit: { type: Boolean, default: false },
    /** Mostrar botón "Eliminar". */
    canDelete: { type: Boolean, default: false },
    /** Deshabilita acciones mientras hay operación en curso. */
    loading: { type: Boolean, default: false },
});

defineEmits(['edit', 'delete', 'clear']);
</script>
