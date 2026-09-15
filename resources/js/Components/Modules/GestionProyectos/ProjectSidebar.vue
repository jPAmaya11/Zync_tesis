<template>
    <!-- Carril que anima su ANCHO (0 → 18rem). Al crecer empuja el contenido hermano
         del contenedor flex: la tabla se desplaza con suavidad, sin overlay. -->
    <div
        :class="[
            'shrink-0 overflow-hidden transition-[width] duration-500 ease-in-out',
            show ? 'w-[19rem]' : 'w-0',
        ]"
    >
        <!-- Contenido con ancho fijo (18rem); el carril mide 19rem → 1rem de separación
             con la tabla a la derecha. No se reacomoda mientras el carril colapsa. -->
        <div
            :class="[
                'w-72 max-h-[calc(100vh-8rem)] flex flex-col rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/40 shadow-sm transition-opacity duration-500',
                show ? 'opacity-100 delay-100' : 'opacity-0',
            ]"
        >
            <!-- Header -->
            <div class="shrink-0 flex items-center justify-between px-4 py-3.5 border-b border-gray-100 dark:border-gray-800">
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">Espacios</h3>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ projects.length }} disponible{{ projects.length !== 1 ? 's' : '' }}</p>
                </div>
                <button
                    type="button"
                    @click="$emit('close')"
                    title="Cerrar"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-200/60 dark:hover:bg-gray-800 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Buscador -->
            <div class="shrink-0 p-3 border-b border-gray-100 dark:border-gray-800">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                    </svg>
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        placeholder="Buscar espacio…"
                        class="w-full h-9 pl-8 pr-2 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                    />
                </div>
            </div>

            <!-- Lista de espacios -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1.5">
                <button
                    v-for="p in filteredProjects"
                    :key="p.key"
                    type="button"
                    @click="onSwitch(p.key)"
                    :class="[
                        'w-full flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-left transition-colors',
                        currentKey === p.key
                            ? 'bg-indigo-50 dark:bg-indigo-900/20 ring-1 ring-indigo-500/20'
                            : 'hover:bg-white dark:hover:bg-gray-800/60',
                    ]"
                >
                    <!-- Ícono -->
                    <img
                        v-if="p.icon && isIconUrl(p.icon)"
                        :src="p.icon"
                        class="w-8 h-8 rounded-lg object-cover shrink-0"
                    />
                    <span
                        v-else-if="p.icon"
                        class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-base shrink-0"
                        >{{ p.icon }}</span
                    >
                    <span
                        v-else
                        class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 flex items-center justify-center font-bold text-[10px] shrink-0"
                        >{{ (p.key || '?').slice(0, 2).toUpperCase() }}</span
                    >

                    <!-- Nombre + descripción -->
                    <div class="min-w-0 flex-1">
                        <span :class="['block text-sm font-semibold truncate', currentKey === p.key ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-800 dark:text-gray-100']">
                            {{ p.name || p.key }}
                        </span>
                        <p v-if="p.description" class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ p.description }}</p>
                    </div>

                    <!-- Check del espacio activo -->
                    <svg v-if="currentKey === p.key" class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <p v-if="!filteredProjects.length" class="text-center text-xs text-gray-400 dark:text-gray-500 py-10">
                    Sin coincidencias.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** Key del proyecto actual (form.project) — para marcar selección. */
    currentKey: { type: String, default: '' },
    /** Lista completa de proyectos (incluye description). */
    projects: { type: Array, default: () => [] },
});

const emit = defineEmits(['switch', 'close']);

const search = ref('');
const searchInput = ref(null);

const filteredProjects = computed(() => {
    const q = search.value.trim().toLowerCase();
    const list = !q
        ? props.projects
        : props.projects.filter(
            (p) =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.key || '').toLowerCase().includes(q) ||
                (p.description || '').toLowerCase().includes(q)
        );
    // El espacio actualmente seleccionado siempre va primero.
    return [...list].sort((a, b) => {
        if (a.key === props.currentKey) return -1;
        if (b.key === props.currentKey) return 1;
        return 0;
    });
});

watch(() => props.show, (val) => {
    if (val) {
        search.value = '';
        nextTick(() => searchInput.value?.focus());
    }
});

function isIconUrl(icon) {
    if (!icon) return false;
    return icon.startsWith('http') || icon.startsWith('data:image');
}

function onSwitch(key) {
    if (key !== props.currentKey) emit('switch', key);
    emit('close');
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
</style>
