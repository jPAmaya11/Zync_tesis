<template>
    <Dropdown align="left" minWidth="240px" search searchPlaceholder="Buscar campo...">
        <template #trigger>
            <button
                class="group inline-flex items-center gap-2 h-9 px-3 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 transition-colors duration-150"
            >
                <svg
                    class="w-4 h-4 text-gray-500 group-hover:text-indigo-500 transition-colors"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"
                    />
                </svg>
                {{ currentLabel || 'Agrupar por...' }}
            </button>
        </template>

        <template #content="{ search }">
            <div class="py-1 max-h-72 overflow-y-auto custom-scrollbar">
                <div
                    class="px-3 py-1.5 text-[10px] font-bold text-gray-400 dark:text-zinc-500 uppercase tracking-wider"
                >
                    Campos disponibles
                </div>
                <button
                    v-for="opt in filteredOptions(search)"
                    :key="opt.value"
                    @click="select(opt.value)"
                    :class="[
                        'w-full text-left px-3 py-2 text-[13px] transition-colors flex items-center justify-between',
                        modelValue === opt.value
                            ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-semibold'
                            : 'text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800',
                    ]"
                >
                    {{ opt.label }}
                    <svg
                        v-if="modelValue === opt.value"
                        class="w-4 h-4 text-indigo-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                </button>

                <div
                    v-if="filteredOptions(search).length === 0"
                    class="px-3 py-4 text-center text-xs text-gray-400 italic"
                >
                    No se encontraron resultados
                </div>
            </div>

            <div
                v-if="modelValue"
                class="border-t border-gray-100 dark:border-zinc-800 mt-1 pt-1"
            >
                <button
                    @click="select('')"
                    class="w-full text-left px-3 py-2 text-[12px] text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                >
                    Quitar agrupación
                </button>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'change']);

const currentLabel = computed(
    () => props.options.find((o) => o.value === props.modelValue)?.label ?? ''
);

function filteredOptions(search) {
    if (!search) return props.options;
    const q = search.toLowerCase();
    return props.options.filter((o) => o.label.toLowerCase().includes(q));
}

function select(value) {
    emit('update:modelValue', value);
    emit('change', value);
}
</script>
