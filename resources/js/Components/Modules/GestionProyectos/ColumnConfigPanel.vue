<template>
    <Teleport to="body">
        <!-- Overlay (solo fade) -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[9998] bg-black/20 dark:bg-black/40"
                @click="close"
            ></div>
        </Transition>

        <!-- Panel (solo slide-x) -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-250 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="show" class="fixed inset-y-0 right-0 z-[9999] flex">
                <div
                    class="relative flex flex-col w-full max-w-sm bg-white dark:bg-gray-900 shadow-2xl h-full overflow-hidden"
                >
                    <!-- Cabecera -->
                    <div
                        class="flex items-center justify-between px-5 py-4 bg-indigo-500 shrink-0"
                    >
                        <div>
                            <h3 class="font-bold text-white text-xl">
                                Configurar campos
                            </h3>
                            <p class="text-indigo-200 text-xs mt-0.5">
                                {{ visibleCount }} visibles ·
                                {{ columns.length }} total
                            </p>
                        </div>
                        <button
                            @click="close"
                            class="p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Lista de columnas -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar py-2">
                        <span
                            class="text-xs text-gray-500 dark:text-gray-400 px-5"
                        >
                            Cambios se aplicarán a todo el mundo
                        </span>
                        <div
                            v-for="(col, idx) in columns"
                            :key="col.key"
                            class="flex items-center gap-3 px-5 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800 group transition"
                        >
                            <Switchtoggle
                                :model-value="col.visible"
                                @update:model-value="$emit('toggle', col.key)"
                                size="sm"
                            />

                            <div class="flex-1 min-w-0">
                                <span
                                    class="text-sm font-medium text-gray-800 dark:text-gray-200"
                                    >{{ col.name }}</span
                                >
                                <span
                                    v-if="col.field_id"
                                    class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300"
                                >
                                    {{ col.type ?? 'text' }}
                                </span>
                            </div>

                            <!-- Flechas de orden -->
                            <div
                                class="flex gap-1 opacity-0 group-hover:opacity-100 transition"
                            >
                                <button
                                    @click="$emit('move-up', col.key)"
                                    :disabled="idx === 0"
                                    class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition"
                                    title="Subir columna"
                                >
                                    <svg
                                        class="w-3.5 h-3.5 text-gray-500"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4.5 15.75l7.5-7.5 7.5 7.5"
                                        />
                                    </svg>
                                </button>
                                <button
                                    @click="$emit('move-down', col.key)"
                                    :disabled="idx === columns.length - 1"
                                    class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition"
                                    title="Bajar columna"
                                >
                                    <svg
                                        class="w-3.5 h-3.5 text-gray-500"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="!columns.length"
                            class="px-5 py-10 text-center text-sm text-gray-400"
                        >
                            No hay columnas configuradas.
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="shrink-0 px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-3"
                    >
                        <PrimaryButton
                            variant="outline"
                            color="indigo"
                            size="md"
                            rounded="lg"
                            block
                            @click="$emit('add-column')"
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
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Crear Campo
                        </PrimaryButton>
                        <PrimaryButton
                            variant="solid"
                            color="indigo"
                            size="md"
                            rounded="lg"
                            @click="close"
                        >
                            Listo
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import Switchtoggle from '@/Components/Inputs/Switchtoggle.vue';

defineProps({
    show: { type: Boolean, default: false },
    /** Array de columnas YA ORDENADAS por `order` (allColumnsSorted del padre). */
    columns: { type: Array, default: () => [] },
    /** Cuántas están con `visible: true` (para mostrar contador). */
    visibleCount: { type: Number, default: 0 },
});

const emit = defineEmits([
    'update:show',
    'toggle',
    'move-up',
    'move-down',
    'add-column',
]);

function close() {
    emit('update:show', false);
}
</script>
