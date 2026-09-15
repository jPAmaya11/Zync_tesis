<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    resumen: { type: Object, default: () => ({}) },
    porEstado: { type: Object, default: () => ({}) },
    porVencer: { type: Array, default: () => [] },
    vencidas: { type: Array, default: () => [] },
    espacios: { type: Array, default: () => [] },
    actividadReciente: { type: Array, default: () => [] },
});

const tarjetas = computed(() => [
    {
        etiqueta: 'Tareas activas',
        valor: props.resumen.activas ?? 0,
        clase: 'text-slate-900 dark:text-slate-100',
    },
    {
        etiqueta: 'Por vencer (7 días)',
        valor: props.resumen.por_vencer ?? 0,
        clase: 'text-amber-600 dark:text-amber-400',
    },
    {
        etiqueta: 'Vencidas',
        valor: props.resumen.vencidas ?? 0,
        clase: 'text-red-600 dark:text-red-400',
    },
    {
        etiqueta: 'Espacios',
        valor: props.resumen.espacios ?? 0,
        clase: 'text-indigo-600 dark:text-indigo-400',
    },
]);

const estados = computed(() => Object.entries(props.porEstado ?? {}));

const hayEstados = computed(() => estados.value.length > 0);

function formatearFecha(valor) {
    if (!valor) return '—';
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return valor;
    return fecha.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatearFechaHora(valor) {
    if (!valor) return '';
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return '';
    return fecha.toLocaleString('es-PE', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function iniciales(nombre) {
    if (!nombre) return '?';
    return nombre
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();
}
</script>

<template>
    <Head title="Inicio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                        Mi resumen
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Tu trabajo pendiente en Gestión de Proyectos
                    </p>
                </div>
                <Link
                    :href="route('gestion-proyectos.index')"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition-colors"
                >
                    Ir al tablero
                </Link>
            </div>
        </template>

        <div class="p-4 sm:p-6 space-y-6">
            <!-- Contadores -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="t in tarjetas"
                    :key="t.etiqueta"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                >
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ t.etiqueta }}
                    </p>
                    <p class="mt-1 text-3xl font-bold" :class="t.clase">
                        {{ t.valor }}
                    </p>
                </div>
            </div>

            <!-- Mis tareas por estado -->
            <section
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
            >
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    Mis tareas por estado
                </h3>
                <div v-if="hayEstados" class="flex flex-wrap gap-2">
                    <span
                        v-for="[estado, total] in estados"
                        :key="estado"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200"
                    >
                        {{ estado }}
                        <span
                            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-indigo-600 text-white text-xs font-bold"
                        >
                            {{ total }}
                        </span>
                    </span>
                </div>
                <p v-else class="text-sm text-gray-400 italic">
                    No tienes tareas activas asignadas.
                </p>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Vencidas -->
                <section
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                >
                    <h3 class="text-sm font-semibold text-red-600 dark:text-red-400 mb-3">
                        Vencidas
                    </h3>
                    <ul v-if="vencidas.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="t in vencidas" :key="t.key" class="py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">
                                        {{ t.summary }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ t.key }} · {{ t.status }}
                                    </p>
                                </div>
                                <span class="text-xs font-semibold text-red-600 dark:text-red-400 whitespace-nowrap">
                                    {{ formatearFecha(t.due_date) }}
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400 italic">
                        Nada vencido. Todo al día.
                    </p>
                </section>

                <!-- Por vencer -->
                <section
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                >
                    <h3 class="text-sm font-semibold text-amber-600 dark:text-amber-400 mb-3">
                        Por vencer (próximos 7 días)
                    </h3>
                    <ul v-if="porVencer.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="t in porVencer" :key="t.key" class="py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">
                                        {{ t.summary }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ t.key }} · {{ t.status }}
                                    </p>
                                </div>
                                <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 whitespace-nowrap">
                                    {{ formatearFecha(t.due_date) }}
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400 italic">
                        Sin vencimientos en los próximos 7 días.
                    </p>
                </section>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Espacios -->
                <section
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                >
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                        Mis espacios
                    </h3>
                    <div v-if="espacios.length" class="flex flex-wrap gap-2">
                        <Link
                            v-for="e in espacios"
                            :key="e.key"
                            :href="route('gestion-proyectos.index', { project: e.key })"
                            class="inline-flex items-center gap-2 rounded-md border border-gray-200 dark:border-gray-600 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        >
                            <span v-if="e.icon">{{ e.icon }}</span>
                            <span class="truncate max-w-48">{{ e.name }}</span>
                        </Link>
                    </div>
                    <p v-else class="text-sm text-gray-400 italic">
                        Todavía no perteneces a ningún espacio.
                    </p>
                </section>

                <!-- Actividad reciente -->
                <section
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                >
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                        Actividad reciente
                    </h3>
                    <ul v-if="actividadReciente.length" class="space-y-3">
                        <li
                            v-for="a in actividadReciente"
                            :key="a.id"
                            class="flex items-start gap-3"
                        >
                            <img
                                v-if="a.avatar_url"
                                :src="a.avatar_url"
                                :alt="a.usuario"
                                class="w-7 h-7 rounded-full object-cover flex-shrink-0"
                            />
                            <span
                                v-else
                                class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold flex items-center justify-center flex-shrink-0"
                            >
                                {{ iniciales(a.usuario) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-800 dark:text-gray-100">
                                    <span class="font-medium">{{ a.usuario || 'Alguien' }}</span>
                                    <span v-if="a.new_status"> movió a «{{ a.new_status }}»</span>
                                    <span v-else> comentó en</span>
                                    <span class="text-gray-500 dark:text-gray-400"> {{ a.tarea_key }}</span>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ a.tarea }}
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    {{ formatearFechaHora(a.created_at) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400 italic">
                        Sin movimientos recientes.
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
