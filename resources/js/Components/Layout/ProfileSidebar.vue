<template>
    <div class="lg:w-80 flex-shrink-0">
        <!-- Contenedor principal con mejoras visuales -->
        <div
            class="bgPrincipal rounded-2xl lg:h-full lg:overflow-y-auto shadow-2xl border border-white/5"
        >
            <div class="lg:h-full">
                <!-- Header del perfil con gr                                        <a
                                            v-if="showContactLinks"
                                            :href="`tel:${user.phone_secondary}`"
                                            class="block text-sm font-medium truncate hover:text-indigo-300 transition-colors duration-300"
                                            :title="user.phone_secondary"
                                        >
                                            {{ user.phone_secondary }}
                                        </a>
                                        <p
                                            v-else
                                            class="text-sm font-medium truncate"
                                            :title="user.phone_secondary"
                                        >
                                            {{ user.phone_secondary }}
                                        </p> -->
                <div
                    class="relative px-6 pt-8 pb-6 border-b border-white/10 bg-gradient-to-b from-white/5 to-transparent"
                >
                    <div class="text-center">
                        <!-- Avatar mejorado con mejor sombra y animaciones -->
                        <div
                            class="relative group mx-auto w-44 h-44 mb-4"
                            style="border-radius: 100%"
                        >
                            <img
                                v-if="user.avatar"
                                :src="`/storage/${user.avatar}`"
                                :alt="user.name"
                                class="w-44 h-44 rounded-full object-cover shadow-2xl ring-4 ring-white/30 transition-all duration-500 group-hover:ring-6 group-hover:ring-white/40"
                                style="border-radius: 100%"
                            />
                            <div
                                v-else
                                class="w-44 h-44 rounded-full bg-gradient-to-br from-indigo-400 via-cyan-500 to-blue-500 flex items-center justify-center shadow-2xl ring-4 ring-white/30 transition-all duration-500 group-hover:ring-6 group-hover:ring-white/40"
                                style="border-radius: 100%"
                            >
                                <span
                                    class="text-6xl font-bold text-white select-none drop-shadow-lg"
                                >
                                    {{ getInitials(user.name) }}
                                </span>
                            </div>

                            <!-- Overlay mejorado para cambiar avatar -->
                            <div
                                v-if="showAvatarUpload"
                                class="absolute inset-0 bg-gradient-to-b from-black/70 to-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-pointer backdrop-blur-md"
                                :class="{
                                    'opacity-100': avatarForm?.processing,
                                }"
                                @click="
                                    !avatarForm?.processing &&
                                        $emit('avatar-click')
                                "
                                style="border-radius: 100%"
                            >
                                <div
                                    v-if="avatarForm?.processing"
                                    class="text-white text-center"
                                >
                                    <svg
                                        class="w-12 h-12 mx-auto animate-spin"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                        ></path>
                                    </svg>
                                    <p class="text-sm mt-3 font-medium">
                                        Subiendo...
                                    </p>
                                </div>
                                <div
                                    v-else
                                    class="text-center transform group-hover:scale-110 transition-transform duration-300"
                                >
                                    <svg
                                        class="w-12 h-12 mx-auto text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                    <p
                                        class="text-sm mt-3 font-medium text-white"
                                    >
                                        Cambiar foto
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Información principal del usuario con mejor espaciado -->
                        <div class="text-white space-y-4">
                            <h1
                                class="text-2xl font-bold tracking-tight leading-tight truncate"
                            >
                                {{ user.name }}
                            </h1>

                            <!-- Estados y verificaciones con mejor diseño -->
                            <div class="flex flex-col gap-2">
                                <!-- Estado público/privado con mejor styling -->
                                <!-- Rol único del usuario -->
                                <div
                                    v-if="
                                        user.role ||
                                        (user.roles && user.roles.length > 0)
                                    "
                                    class="flex justify-center"
                                >
                                    <span
                                        class="inline-block px-3 py-1.5 text-xs bg-white/15 backdrop-blur-sm text-white rounded-full font-semibold border border-white/25 hover:bg-white/25 transition-all duration-300 hover:scale-105 truncate max-w-32"
                                        :title="
                                            user.role?.name ||
                                            (user.roles && user.roles[0]?.name)
                                        "
                                    >
                                        {{
                                            user.role?.name ||
                                            (user.roles && user.roles[0]?.name)
                                        }}
                                    </span>
                                </div>

                                <!-- Fecha de registro con diseño mejorado -->
                                <div
                                    v-if="showUserStats"
                                    class="bg-white/10 backdrop-blur-md rounded-lg px-3 py-2 border border-white/20 mx-auto"
                                >
                                    <p
                                        class="text-xs text-white/80 font-medium text-center"
                                    >
                                        Miembro desde
                                    </p>
                                    <p
                                        class="text-xs font-bold text-white text-center"
                                    >
                                        {{ formatDate(user.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de contacto expandida con mejor diseño -->
                <div class="px-6 py-4 space-y-4 profile-info-scroll">
                    <!-- Sección de contacto con header mejorado -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div
                                class="w-6 h-0.5 bg-gradient-to-r from-white/40 to-white/20 rounded-full"
                            ></div>
                            <h3
                                class="text-white font-semibold text-xs uppercase tracking-wider"
                            >
                                Contacto
                            </h3>
                        </div>

                        <!-- Cards de contacto con mejor diseño -->
                        <div class="space-y-2">
                            <!-- Email -->
                            <div
                                class="bg-white/10 backdrop-blur-sm rounded-lg p-3 border border-white/20 hover:bg-white/[0.15] transition-all duration-300 group"
                            >
                                <div class="flex items-center gap-3 text-white">
                                    <div
                                        class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-white/30 transition-all duration-300"
                                    >
                                        <svg
                                            class="w-4 h-4 text-white"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs text-white/90 font-medium mb-0.5"
                                        >
                                            Email
                                        </p>
                                        <a
                                            v-if="showContactLinks"
                                            :href="`mailto:${user.email}`"
                                            class="block text-sm font-semibold truncate hover:text-white/90 transition-colors duration-300"
                                            :title="user.email"
                                        >
                                            {{ user.email }}
                                        </a>
                                        <p
                                            v-else
                                            class="text-sm font-semibold truncate"
                                            :title="user.email"
                                        >
                                            {{ user.email }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Teléfono -->
                            <div
                                v-if="user.phone"
                                class="bg-white/10 backdrop-blur-sm rounded-lg p-3 border border-white/20 hover:bg-white/[0.15] transition-all duration-300 group"
                            >
                                <div class="flex items-center gap-3 text-white">
                                    <div
                                        class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-white/30 transition-all duration-300"
                                    >
                                        <svg
                                            class="w-4 h-4 text-white"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs text-white/90 font-medium mb-0.5"
                                        >
                                            Teléfono
                                        </p>
                                        <a
                                            v-if="showContactLinks"
                                            :href="`tel:${user.phone}`"
                                            class="block text-sm font-semibold truncate hover:text-white/90 transition-colors duration-300"
                                            :title="user.phone"
                                        >
                                            {{ user.phone }}
                                        </a>
                                        <p
                                            v-else
                                            class="text-sm font-semibold truncate"
                                            :title="user.phone"
                                        >
                                            {{ user.phone }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tipo de Documento y Número -->
                            <div
                                v-if="user.numero_documento"
                                class="bg-white/10 backdrop-blur-sm rounded-lg p-3 border border-white/20 hover:bg-white/[0.15] transition-all duration-300 group"
                            >
                                <div class="flex items-center gap-3 text-white">
                                    <div
                                        class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-white/30 transition-all duration-300"
                                    >
                                        <svg
                                            class="w-4 h-4 text-white"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs text-white/90 font-medium mb-0.5"
                                        >
                                            Documento
                                        </p>
                                        <div class="text-sm font-semibold">
                                            <div
                                                class="truncate"
                                                :title="user.numero_documento"
                                            >
                                                {{ user.numero_documento }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción con mejor diseño -->
                    <div
                        v-if="$slots.actions && $slots.actions().length > 0"
                        class="space-y-3"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="w-6 h-0.5 bg-gradient-to-r from-white/40 to-white/20 rounded-full"
                            ></div>
                            <h3
                                class="text-white font-semibold text-xs uppercase tracking-wider"
                            >
                                Acciones
                            </h3>
                        </div>
                        <div class="flex flex-col gap-2">
                            <slot name="actions"></slot>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    showAvatarUpload: {
        type: Boolean,
        default: false,
    },
    showProfileStatus: {
        type: Boolean,
        default: false,
    },
    showUserStats: {
        type: Boolean,
        default: false,
    },
    showContactLinks: {
        type: Boolean,
        default: false,
    },
    avatarForm: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["avatar-click"]);

function getInitials(name) {
    return name
        .split(" ")
        .map((word) => word[0])
        .join("")
        .toUpperCase()
        .substring(0, 2);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString("es-ES", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
}
</script>

<style scoped>
/* ============================== */
/* SCROLL PERSONALIZADO Y MEJORADO */
/* ============================== */
.profile-info-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.profile-info-scroll::-webkit-scrollbar {
    width: 6px;
}

.profile-info-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.profile-info-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.profile-info-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* ============================== */
/* ANIMACIONES MEJORADAS */
/* ============================== */
.profile-info-scroll > div {
    transition: all 0.2s ease-in-out;
}

.profile-info-scroll > div:hover {
    transform: translateX(2px);
}

/* Efectos de glassmorphism mejorados */
.bg-white\/5 {
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.bg-white\/10 {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.bg-white\/20 {
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
}

/* ============================== */
/* ANIMACIONES DE ENTRADA ESCALONADAS - DESHABILITADAS */
/* ============================== */
/* Animaciones de entrada removidas para mejor experiencia del usuario */

/* ============================== */
/* EFECTOS HOVER MEJORADOS */
/* ============================== */
.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}

.group:hover .group-hover\:scale-105 {
    transform: scale(1.05);
}

/* ============================== */
/* GRADIENTES PERSONALIZADOS */
/* ============================== */
.bg-gradient-to-br {
    background-image: linear-gradient(
        to bottom right,
        var(--tw-gradient-stops)
    );
}

/* ============================== */
/* MEJORAS DE ACCESIBILIDAD */
/* ============================== */
@media (prefers-reduced-motion: reduce) {
    .group-hover\:scale-110,
    .group-hover\:scale-105,
    .animate-pulse {
        animation: none !important;
        transition: none !important;
    }
}

/* ============================== */
/* RESPONSIVE BREAKPOINTS */
/* ============================== */
@media (max-width: 1024px) {
    .profile-info-scroll {
        max-height: none;
    }
}
</style>
