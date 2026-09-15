<template>
    <ModalView
        :show="show"
        title="Usuarios del rol"
        :subtitle="role ? role.name : ''"
        size="lg"
        :hide-footer="true"
        @close="$emit('close')"
    >
        <!-- Cargando (solo carga inicial; en paginación se mantiene la lista) -->
        <div v-if="loading && !users.length" class="flex items-center justify-center py-12">
            <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
            <span class="ml-2 text-sm text-gray-500">Cargando usuarios…</span>
        </div>

        <!-- Error -->
        <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg px-3 py-2">
            {{ error }}
        </p>

        <!-- Sin usuarios -->
        <div v-else-if="!users.length" class="flex flex-col items-center justify-center py-12 gap-2 text-center">
            <svg class="w-10 h-10 text-gray-200 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />
            </svg>
            <p class="text-sm text-gray-400">Ningún usuario tiene este rol</p>
        </div>

        <!-- Lista (altura fija cuando hay varias páginas → el modal no cambia de tamaño al paginar) -->
        <ul
            v-else
            :class="[
                'divide-y divide-gray-100 dark:divide-gray-700/60',
                meta.last_page > 1 ? 'h-[55vh] overflow-y-auto custom-scrollbar' : '-my-1',
                loading ? 'opacity-50 transition-opacity' : '',
            ]"
        >
            <li v-for="u in users" :key="u.id" class="flex items-center gap-3 py-2.5">
                <img
                    v-if="u.avatar"
                    :src="u.avatar"
                    class="w-9 h-9 rounded-full object-cover shrink-0"
                    alt=""
                />
                <span
                    v-else
                    class="w-9 h-9 rounded-full bg-indigo-500 text-white text-xs font-bold inline-flex items-center justify-center shrink-0"
                >{{ initials(u.name) }}</span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ u.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ u.email }}</p>
                </div>

                <span
                    :class="[
                        'shrink-0 px-2 py-0.5 text-[10px] font-semibold rounded-full border',
                        u.active
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30'
                            : 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:border-gray-600',
                    ]"
                >{{ u.active ? 'Activo' : 'Inactivo' }}</span>
            </li>
        </ul>

        <!-- Paginación (20 por página) -->
        <div
            v-if="!loading && !error && meta.total"
            class="mt-3 flex items-center justify-between gap-2 pt-3 border-t border-gray-100 dark:border-gray-700"
        >
            <span class="text-xs text-gray-400">
                {{ meta.from }}–{{ meta.to }} de {{ meta.total }} usuario{{ meta.total === 1 ? '' : 's' }}
            </span>
            <div v-if="meta.last_page > 1" class="flex items-center gap-1">
                <button
                    type="button"
                    @click="prev"
                    :disabled="meta.current_page <= 1"
                    class="inline-flex items-center justify-center h-7 px-2 rounded-md text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <span class="text-xs font-medium text-gray-500 tabular-nums px-1.5">{{ meta.current_page }} / {{ meta.last_page }}</span>
                <button
                    type="button"
                    @click="next"
                    :disabled="meta.current_page >= meta.last_page"
                    class="inline-flex items-center justify-center h-7 px-2 rounded-md text-xs font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        </div>
    </ModalView>
</template>

<script setup>
import ModalView from '@/Components/Modals/ModalView.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    role: { type: Object, default: null },
});

defineEmits(['close']);

const users = ref([]);
const loading = ref(false);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });

function initials(name) {
    return (name || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
}

async function fetchUsers(page = 1) {
    if (!props.role) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.get(
            route('roles.usuarios', { role: props.role.id, page })
        );
        users.value = data.users || [];
        meta.value = data.meta || { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 };
    } catch (e) {
        error.value = e.response ? 'No se pudieron cargar los usuarios.' : 'Error de conexión.';
        users.value = [];
    } finally {
        loading.value = false;
    }
}

function prev() {
    if (meta.value.current_page > 1) fetchUsers(meta.value.current_page - 1);
}
function next() {
    if (meta.value.current_page < meta.value.last_page) fetchUsers(meta.value.current_page + 1);
}

// Cargar la primera página al abrir el modal con un rol.
watch(
    () => props.show,
    (val) => {
        if (val && props.role) fetchUsers(1);
    }
);
</script>
