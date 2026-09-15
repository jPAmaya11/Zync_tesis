<template>
    <div v-if="currentProject" class="flex items-center gap-3">
        <!-- El nombre del espacio es clickable: alterna el sidebar deslizable de espacios. -->
        <button
            v-if="projects.length"
            type="button"
            @click="$emit('toggle')"
            title="Cambiar de espacio"
            :class="[
                'group flex items-center gap-2 rounded-lg px-2 py-1.5 cursor-pointer transition-colors',
                open ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-800',
            ]"
        >
            <img
                v-if="currentProject.icon && isIconUrl(currentProject.icon)"
                :src="currentProject.icon"
                class="w-8 h-8 rounded-sm object-cover"
            />
            <span
                v-else-if="currentProject.icon"
                class="w-9 h-9 rounded-sm bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-lg"
                >{{ currentProject.icon }}</span
            >
            <span :class="['text-lg font-bold transition-colors', open ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400']">{{
                currentProject.name || currentProject.key
            }}</span>
            <svg
                :class="[
                    'w-4 h-4 transition-transform duration-300',
                    open ? 'rotate-180 text-indigo-500' : 'text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300',
                ]"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Sin lista de espacios disponible: solo el nombre (sin selector). -->
        <div v-else class="flex items-center gap-2">
            <img
                v-if="currentProject.icon && isIconUrl(currentProject.icon)"
                :src="currentProject.icon"
                class="w-8 h-8 rounded-sm object-cover"
            />
            <span
                v-else-if="currentProject.icon"
                class="w-9 h-9 rounded-sm bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-lg"
                >{{ currentProject.icon }}</span
            >
            <span class="text-lg text-gray-600 dark:text-gray-400 font-bold">{{
                currentProject.name || currentProject.key
            }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    /** Proyecto actualmente seleccionado (objeto). */
    currentProject: { type: Object, default: null },
    /** Lista completa de proyectos (incluye description). */
    projects: { type: Array, default: () => [] },
    /** Estado del sidebar (para rotar el chevron / resaltar el trigger). */
    open: { type: Boolean, default: false },
});

defineEmits(['toggle']);

function isIconUrl(icon) {
    if (!icon) return false;
    return icon.startsWith('http') || icon.startsWith('data:image');
}
</script>
