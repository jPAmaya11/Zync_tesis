<template>
    <div
        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center"
    >
        <div
            class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-gradient-to-br from-indigo-50 to-cyan-50 dark:from-indigo-900/30 dark:to-cyan-900/20 flex items-center justify-center"
        >
            <svg
                class="w-10 h-10 text-indigo-500 dark:text-indigo-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"
                />
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
            Sin proyectos aún
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
            Comienza creando tu primer espacio de proyecto.
            Podrás organizar issues, asignar responsables y dar seguimiento desde aquí.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <button
                v-if="canCreate"
                @click="$emit('create')"
                class="inline-flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-lg shadow-sm hover:opacity-90 transition-opacity"
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
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Crear mi primer espacio
            </button>

            <!-- Equipos son globales: se pueden gestionar aunque no haya espacios todavía. -->
            <button
                v-if="canCreate"
                @click="$emit('manage-teams')"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <svg
                    class="w-4 h-4 text-indigo-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
                Configurar equipos
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    canCreate: { type: Boolean, default: false },
});

defineEmits(['create', 'manage-teams']);
</script>
