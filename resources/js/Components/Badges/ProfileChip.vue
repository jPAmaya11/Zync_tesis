<template>
    <div class="relative">
        <!-- ProfileChip unificado: avatar + nombre en un solo elemento -->
        <button
            ref="triggerRef"
            class="relative inline-flex items-center gap-2 bg-white dark:bg-gray-800 rounded-full py-1 px-1.5 border shadow-sm transition-all duration-300 transform group"
            :class="[
                props.maxWidth,
                {
                    'opacity-60 cursor-default': user.isPlaceholder,
                    'cursor-pointer hover:shadow-md': !user.isPlaceholder,
                    'border-yellow-200 bg-yellow-50 dark:border-yellow-200 dark:bg-yellow-50':
                        !user.isPlaceholder && displayInfo.isRestricted,
                    'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800':
                        !user.isPlaceholder && !displayInfo.isRestricted,
                },
            ]"
            @click="!user.isPlaceholder && toggleTooltip($event)"
            :title="getTitle()"
            style="border-radius: 9999px"
        >
            <!-- Avatar circular -->
            <div
                class="relative flex items-center justify-center text-white text-xs font-bold"
                :class="{
                    'bg-gray-400': user.isPlaceholder,
                    bgPrincipal: !user.isPlaceholder,
                }"
                :style="{
                    width: '28px',
                    height: '28px',
                    borderRadius: '100%',
                    backgroundImage:
                        user.avatar && !user.isPlaceholder
                            ? `url(/storage/${user.avatar})`
                            : 'none',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundRepeat: 'no-repeat',
                    imageRendering: 'crisp-edges',
                }"
            >
                <!-- Contenido del avatar -->
                <span
                    v-if="!user.avatar || user.isPlaceholder"
                    class="text-[11px]"
                >
                    {{ getInitials(displayInfo.name) }}
                </span>

                <!-- Indicador de estado en la esquina -->
                <div
                    class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border transition-all duration-300"
                    :class="[
                        user.active ? 'bg-green-500' : 'bg-red-500',
                        'border-white dark:border-gray-800',
                    ]"
                    :title="user.active ? 'Usuario activo' : 'Usuario inactivo'"
                ></div>

                <!-- Indicador de restricción -->
                <div
                    v-if="displayInfo.isRestricted"
                    class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-orange-500 rounded-full border border-white dark:border-gray-800 flex items-center justify-center"
                    title="Acceso restringido"
                >
                    <svg
                        class="w-1.5 h-1.5 text-white"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
            </div>

            <!-- Nombre del usuario integrado -->
            <span
                class="text-xs font-medium pr-2 transition-colors duration-300 truncate flex-1 min-w-0"
                :class="{
                    'opacity-60': user.isPlaceholder,
                    'text-blue-600':
                        !user.isPlaceholder && !displayInfo.isRestricted,
                    'text-orange-600':
                        !user.isPlaceholder && displayInfo.isRestricted,
                }"
            >
                {{ displayInfo.name }}
            </span>
        </button>

        <!-- Tooltip/Modal pequeño - Usando Teleport + Floating UI -->
        <Teleport to="body">
            <div
                v-if="showTooltipState && !user.isPlaceholder"
                ref="tooltipRef"
                :style="floatingStyles"
                class="fixed z-[9999] w-80 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-100 dark:border-gray-700 tooltip-interactive backdrop-blur-sm p-5"
                @click.stop
            >
                <!-- Header del tooltip -->
                <div
                    class="flex items-center gap-4 mb-4 pb-3 border-b border-gray-100 dark:border-gray-700"
                >
                    <div
                        class="relative flex items-center justify-center text-white text-base font-bold shadow-lg ring-2 ring-white dark:ring-gray-600"
                        :class="
                            displayInfo.showDetails
                                ? 'bgPrincipal'
                                : 'bg-gray-400'
                        "
                        :style="{
                            width: '48px',
                            height: '48px',
                            borderRadius: '100%',
                            backgroundImage:
                                user.avatar && displayInfo.showDetails
                                    ? `url(/storage/${user.avatar})`
                                    : 'none',
                            backgroundSize: 'cover',
                            backgroundPosition: 'center',
                            backgroundRepeat: 'no-repeat',
                        }"
                    >
                        <span v-if="!user.avatar || !displayInfo.showDetails">
                            {{ getInitials(user.name) }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3
                            class="text-base font-bold text-gray-900 dark:text-white truncate mb-1"
                        >
                            {{ user.name }}
                        </h3>
                        <p
                            v-if="displayInfo.showEmail"
                            class="text-sm text-gray-600 dark:text-gray-300 truncate mb-1"
                        >
                            {{ user.email }}
                        </p>
                        <p
                            v-else
                            class="text-sm text-gray-400 dark:text-gray-500 italic mb-1"
                        >
                            Email oculto
                        </p>
                        <div class="flex items-center gap-2">
                            <div
                                class="w-2 h-2 rounded-full"
                                :class="
                                    user.active ? 'bg-green-500' : 'bg-red-500'
                                "
                            ></div>
                            <span
                                class="text-xs font-medium"
                                :class="
                                    user.active
                                        ? 'text-green-700'
                                        : 'text-red-700'
                                "
                            >
                                {{ user.active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Información adicional (solo si tiene permisos) -->
                <div v-if="displayInfo.showDetails" class="space-y-3 mb-4">
                    <div
                        v-if="user.roles && user.roles.length > 0"
                        class="flex items-start gap-2"
                    >
                        <svg
                            class="w-3 h-3 text-gray-400 mt-0.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="role in user.roles.slice(0, 3)"
                                :key="role.id"
                                class="inline-block text-xs bg-blue-100 text-blue-700 rounded-full px-2 py-0.5"
                            >
                                {{ role.name }}
                            </span>
                            <span
                                v-if="user.roles.length > 3"
                                class="text-xs text-gray-500"
                            >
                                +{{ user.roles.length - 3 }} más
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="user.last_login_at"
                        class="flex items-center gap-2"
                    >
                        <svg
                            class="w-3 h-3 text-gray-400 dark:text-gray-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <span class="text-xs text-gray-600 dark:text-gray-300">
                            Último acceso:
                            {{ formatDate(user.last_login_at) }}
                        </span>
                    </div>
                </div>

                <!-- Mensaje de acceso restringido -->
                <div v-if="!displayInfo.showDetails" class="mb-4">
                    <div
                        class="flex items-center gap-2 p-2.5 bg-yellow-50 border border-yellow-200 rounded-lg"
                    >
                        <svg
                            class="w-4 h-4 text-orange-600 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                            />
                        </svg>
                        <p class="text-orange-700 text-xs font-medium">
                            Perfil con acceso restringido
                        </p>
                    </div>
                </div>

                <!-- Acciones -->
                <div
                    class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Información completa
                    </div>

                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { flip, offset, shift, useFloating } from '@floating-ui/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useTooltipManager } from '../../Composables/useTooltipManager.js';

const props = defineProps({
    user: Object,
    maxWidth: {
        type: String,
        default: 'max-w-40',
    },
});

// Referencias para Floating UI
const triggerRef = ref(null);
const tooltipRef = ref(null);

// Floating UI
const { floatingStyles, update } = useFloating(triggerRef, tooltipRef, {
    middleware: [offset(8), flip(), shift({ padding: 8 })],
});

const displayInfo = computed(() => ({
    showName: true,
    showEmail: true,
    showDetails: true,
    showTooltip: true,
    name: props.user.name || 'Usuario',
    isRestricted: false,
}));

// Usar el gestor global de tooltips
const { activeTooltipId, openTooltip, closeTooltip, isActive } =
    useTooltipManager();

// ID único para este tooltip
const tooltipId = ref(`tooltip-${Math.random().toString(36).substr(2, 9)}`);

// Estado reactivo basado en el gestor global
const showTooltipState = computed(
    () =>
        isActive(tooltipId.value) &&
        displayInfo.value.showTooltip &&
        !props.user.isPlaceholder
);

// Obtener el título apropiado
function getTitle() {
    if (displayInfo.value.isRestricted) {
        return 'Perfil con acceso restringido - Haz clic para ver información básica';
    }
    return 'Haz clic para ver detalles del perfil';
}

function getInitials(name) {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
}

function formatDate(dateString) {
    if (!dateString) return 'Nunca';

    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));

    if (diffInHours < 24) {
        return `Hace ${diffInHours}h`;
    } else if (diffInHours < 168) {
        // 7 días
        const days = Math.floor(diffInHours / 24);
        return `Hace ${days}d`;
    } else {
        return date.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }
}

function toggleTooltip(event) {
    event.stopPropagation();

    // No permitir tooltip si no tiene permisos para ver detalles
    if (!displayInfo.value.showTooltip) {
        return;
    }

    if (isActive(tooltipId.value)) {
        // Si este tooltip está abierto, cerrarlo
        closeTooltip();
    } else {
        // Abrir este tooltip (esto cerrará automáticamente cualquier otro abierto)
        openTooltip(tooltipId.value);
    }
}

function closeCurrentTooltip() {
    closeTooltip();
}

// Cerrar al hacer click fuera
const handleClickOutside = (e) => {
    if (
        triggerRef.value &&
        tooltipRef.value &&
        !triggerRef.value.contains(e.target) &&
        !tooltipRef.value.contains(e.target)
    ) {
        closeTooltip();
    }
};

// Watch para actualizar posición cuando cambia el tooltip
watch(showTooltipState, (isShowing) => {
    if (isShowing) {
        update();
        setTimeout(() => update(), 0);
        document.addEventListener('click', handleClickOutside);
    } else {
        document.removeEventListener('click', handleClickOutside);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
/* Mejorar calidad de renderizado para imágenes pequeñas */
div[style*='background-image'] {
    image-rendering: crisp-edges;
    backface-visibility: hidden;
    transform: translateZ(0);
    will-change: transform;
}

/* Tooltip completamente interactivo */
.tooltip-interactive {
    pointer-events: auto !important;
    cursor: default;
    user-select: text;
}

/* Asegurar que todos los elementos dentro del tooltip sean clickeables */
.tooltip-interactive * {
    pointer-events: auto !important;
    user-select: text;
}

/* Específicamente para links y botones */
.tooltip-interactive a,
.tooltip-interactive button {
    pointer-events: auto !important;
    cursor: pointer !important;
    user-select: none;
}

/* Sombra mejorada para el tooltip */
.tooltip-interactive {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25),
        0 10px 20px -5px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(16px);
}

/* Mejorar spacing y legibilidad */
.tooltip-interactive .text-xs {
    line-height: 1.5;
}

.tooltip-interactive .text-sm {
    line-height: 1.6;
}
</style>
