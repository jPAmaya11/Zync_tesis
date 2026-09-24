<!-- =========================
    1. SCRIPT SETUP
========================= -->
<script setup>
// =======================
// 1. IMPORTS Y DEPENDENCIAS
// =======================
import ApplicationLogo from '@/Components/Layout/ApplicationLogo.vue';
import ApplicationLogoMini from '@/Components/Layout/ApplicationLogoMini.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import DropdownLink from '@/Components/Navigation/DropdownLink.vue';
import NavLink from '@/Components/Navigation/NavLink.vue';
import DataTool from '@/Components/Utilities/DataTool.vue';
import SweetAlert from '@/Components/Utilities/SweetAlert.vue';
import ChatIAWidget from '@/Components/Modules/GestionProyectos/ChatIAWidget.vue';
import { useDarkMode } from '@/Composables/useDarkMode';
import { Link, usePage } from '@inertiajs/vue3';
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue';
import 'overlayscrollbars/overlayscrollbars.css';
import { computed, onMounted, ref } from 'vue';

// =======================
// 2. COMPOSABLES Y PROPIEDADES BÁSICAS
// =======================
const page = usePage();

// Función helper para verificar permisos
const canDo = (key) => {
    return !!(page.props.can && page.props.can[key]);
};

// Dark mode
const { isDark, toggleDark } = useDarkMode();

// =======================
// 3. ESTADO REACTIVO - SIDEBAR Y UI
// =======================
const ui = ref({
    isSidebarOpen: false,
    mobileSidebarOpen: false,
});

// =======================
// 4. ESTADO REACTIVO - TOOLTIP
// =======================
const tooltip = ref({
    show: false,
    label: '',
    top: 0,
    left: 0,
});
let tooltipTimeout = null;

// =======================
// 5. FUNCIONES DE SIDEBAR
// =======================
function toggleSidebar() {
    ui.value.isSidebarOpen = !ui.value.isSidebarOpen;
}

function toggleMobileSidebar() {
    ui.value.mobileSidebarOpen = !ui.value.mobileSidebarOpen;
}

// =======================
// 6. FUNCIONES DE TOOLTIP
// =======================
function showTooltipReporte(e, label) {
    if (tooltipTimeout) clearTimeout(tooltipTimeout);

    const btn = e.currentTarget;
    const rect = btn.getBoundingClientRect();
    const sidebar = document.querySelector('aside');
    const sidebarRect = sidebar.getBoundingClientRect();

    // Calcula posición absoluta respecto a la ventana, pegado al sidebar
    let left = sidebarRect.right;

    // Si el sidebar está muy pegado al borde derecho, que no se corte
    const maxLeft = window.innerWidth - 220; // 220px de ancho máx del tooltip
    if (left > maxLeft) left = maxLeft;

    tooltip.value = {
        show: true,
        label,
        top: rect.top + rect.height / 2,
        left,
    };
}

function hideTooltipReporte() {
    tooltipTimeout = setTimeout(() => {
        tooltip.value.show = false;
    }, 80);
}

// =======================
// 7. CICLO DE VIDA
// =======================
onMounted(() => {
    window.addEventListener('resize', handleWindowResize);
});

function handleWindowResize() {
    if (window.innerWidth >= 1024) {
        ui.value.mobileSidebarOpen = false;
    }
}

// =======================
// 8. EXPOSICIÓN DE PROPIEDADES PARA EL TEMPLATE
// =======================
const isSidebarOpen = computed(() => ui.value.isSidebarOpen);
const mobileSidebarOpen = computed(() => ui.value.mobileSidebarOpen);
</script>

<!-- =========================
    2. TEMPLATE
========================= -->
<template>
    <div
        class="min-h-screen bg-gray-100 dark:bg-gray-900 flex transition-colors duration-300"
    >
        <!-- MOBILE OVERLAY -->
        <div
            v-if="mobileSidebarOpen"
            @click="toggleMobileSidebar"
            class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
        ></div>

        <!-- ===== SIDEBAR ===== -->
        <aside
            :class="[
                'bgPrincipal customMenu fixed inset-y-0 left-0 z-50 shadow-lg flex flex-col transition-all duration-300 ease-in-out ',
                mobileSidebarOpen
                    ? 'translate-x-0'
                    : '-translate-x-full lg:translate-x-0',
                isSidebarOpen ? 'w-64 menuAbierto' : 'w-20',
            ]"
            style="position: fixed"
        >
            <!-- Logo -->
            <div
                class="flex items-center justify-center p-4 border-b border-white/10 h-16 relative overflow-hidden group"
            >
                <Link :href="route('dashboard')" class="flex items-center">
                    <ApplicationLogo
                        v-if="isSidebarOpen"
                        :variant="'white'"
                        class="block h-9 w-auto"
                    />

                    <ApplicationLogoMini
                        v-else
                        class="block h-9 w-auto text-white"
                    />
                </Link>
            </div>
            <button
                @click="toggleSidebar"
                class="w-full flex items-center px-3 py-3 hover:bg-white/10 transition"
                :class="
                    isSidebarOpen ? 'justify-start pl-26' : 'sin-justify-center'
                "
            >
                <svg
                    v-if="isSidebarOpen"
                    class="svgMenu"
                    style="width: 26px; height: 26px; color: #fff"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 512 512"
                >
                    <path
                        fill="white"
                        d="M0 80c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 96C7.2 96 0 88.8 0 80zM64 240c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L80 256c-8.8 0-16-7.2-16-16zM448 400c0 8.8-7.2 16-16 16L16 416c-8.8 0-16-7.2-16-16s7.2-16 16-16l416 0c8.8 0 16 7.2 16 16z"
                    />
                </svg>
                <svg
                    v-else
                    class="svgMenu"
                    style="width: 26px; height: 26px; color: #fff"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 448 512"
                >
                    <path
                        fill="white"
                        d="M0 80c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 96C7.2 96 0 88.8 0 80zM0 240c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 256c-8.8 0-16-7.2-16-16zM448 400c0 8.8-7.2 16-16 16L16 416c-8.8 0-16-7.2-16-16s7.2-16 16-16l416 0c8.8 0 16 7.2 16 16z"
                    />
                </svg>
                <span v-if="isSidebarOpen" class="ml-2 text-sm text-white"
                    >Cerrar menú</span
                >
            </button>

            <!-- Menu -->
            <OverlayScrollbarsComponent
                element="nav"
                :options="{
                    scrollbars: {
                        theme: 'os-theme-light',
                        visibility: 'auto',
                        autoHide: 'leave',
                        autoHideDelay: 800,
                    },
                    overflow: {
                        x: 'hidden',
                    },
                }"
                class="flex-1 min-h-0"
                defer
            >
                <link
                    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded"
                    rel="stylesheet"
                />



                <div class="separador separadorAdmin"></div>

                <NavLink
                    v-if="canDo('gestion-proyectos.ver')"
                    :href="route('gestion-proyectos.index')"
                    :active="route().current('gestion-proyectos.*')"
                    class="w-full flex items-center px-3 py-3 hover:bg-white/10 transition group relative"
                    :class="
                        isSidebarOpen
                            ? 'justify-start pl-26'
                            : 'sin-justify-center'
                    "
                    @mouseenter="
                        !isSidebarOpen &&
                            showTooltipReporte($event, 'Gestión de Proyectos')
                    "
                    @mouseleave="!isSidebarOpen && hideTooltipReporte()"
                    @focus="
                        !isSidebarOpen &&
                            showTooltipReporte($event, 'Gestión de Proyectos')
                    "
                    @blur="!isSidebarOpen && hideTooltipReporte()"
                >
                    <svg
                        class="w-6 h-6 text-white flex-shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <transition name="fade-slide">
                        <span
                            v-if="isSidebarOpen"
                            class="ml-3 text-white whitespace-nowrap menu-text"
                        >
                            Gestión de Proyectos
                        </span>
                    </transition>
                </NavLink>

                <NavLink
                    v-if="canDo('roles.ver')"
                    :href="route('roles.index')"
                    :active="route().current('roles.index')"
                    class="w-full flex items-center px-3 py-3 hover:bg-white/10 transition group relative"
                    :class="
                        isSidebarOpen
                            ? 'justify-start pl-26'
                            : 'sin-justify-center'
                    "
                    @mouseenter="
                        !isSidebarOpen &&
                            showTooltipReporte($event, 'Asignación de Roles')
                    "
                    @mouseleave="!isSidebarOpen && hideTooltipReporte()"
                    @focus="
                        !isSidebarOpen &&
                            showTooltipReporte($event, 'Asignación de Roles')
                    "
                    @blur="!isSidebarOpen && hideTooltipReporte()"
                >
                    <svg
                        class="w-6 h-6 text-white flex-shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <transition name="fade-slide">
                        <span
                            v-if="isSidebarOpen"
                            class="ml-3 text-white menu-text"
                        >
                            Asignación de Roles
                        </span>
                    </transition>
                </NavLink>

                <NavLink
                    v-if="canDo('usuarios.ver')"
                    :href="route('users.index')"
                    :active="route().current('users.index')"
                    class="w-full flex items-center px-3 py-3 hover:bg-white/10 transition group relative"
                    :class="
                        isSidebarOpen
                            ? 'justify-start pl-26'
                            : 'sin-justify-center'
                    "
                    @mouseenter="
                        !isSidebarOpen &&
                            showTooltipReporte($event, 'Gestión de Usuarios')
                    "
                    @mouseleave="!isSidebarOpen && hideTooltipReporte()"
                    @focus="
                        !isSidebarOpen &&
                            showTooltipReporte($event, 'Gestión de Usuarios')
                    "
                    @blur="!isSidebarOpen && hideTooltipReporte()"
                >
                    <svg
                        class="w-6 h-6 text-white flex-shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>

                    <transition name="fade-slide">
                        <span
                            v-if="isSidebarOpen"
                            class="ml-3 text-white whitespace-nowrap menu-text"
                        >
                            Gestión de Usuarios
                        </span>
                    </transition>
                </NavLink>
            </OverlayScrollbarsComponent>
        </aside>

        <!-- Tooltip del sidebar colapsado -->
        <DataTool
            v-if="tooltip.show && !isSidebarOpen"
            :label="tooltip.label"
            :show="true"
            :style="{
                position: 'fixed',
                top: tooltip.top + 'px',
                left: tooltip.left + 'px',
                zIndex: 9999,
                pointerEvents: 'none',
                transition: 'opacity 0.15s',
                visibility: 'visible',
                opacity: 1,
                color: 'white',
            }"
        />

        <!-- ===== MAIN CONTENT ===== -->
        <div
            :class="[
                'flex-1 flex flex-col overflow-hidden transition-all duration-300 ease-in-out',
                isSidebarOpen ? 'lg:pl-64' : 'lg:pl-20',
            ]"
        >
            <!-- ===== TOP BAR ===== -->
            <header
                class="bg-white dark:bg-gray-800 shadow-sm z-20 transition-colors duration-300"
            >
                <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16 items-center">
                        <!-- Sidebar Toggle (Mobile) -->
                        <button
                            @click="toggleMobileSidebar"
                            class="md:hidden text-white hover:colorPrincipal mr-2"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="#000"
                                style="color: #000"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <!-- Portal para acciones de página ( config rotación PBI) -->
                        <div id="topbar-page-actions" class="flex items-center gap-2 relative z-20"></div>

                        <div class="flex-1"></div>

                        <!-- Menú de Usuario -->
                        <div class="flex items-center gap-2">
                            <!-- Toggle Modo Oscuro -->
                            <button
                                @click="toggleDark"
                                class="relative group flex items-center justify-center w-10 h-10 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-300"
                                :title="isDark ? 'Modo Claro' : 'Modo Oscuro'"
                            >
                                <!-- Icono Sol (Modo Claro) -->
                                <svg
                                    v-if="!isDark"
                                    class="w-5 h-5 text-amber-500 transition-transform duration-300 group-hover:rotate-45"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"
                                    />
                                </svg>

                                <!-- Icono Luna (Modo Oscuro) -->
                                <svg
                                    v-else
                                    class="w-5 h-5 text-indigo-400 transition-transform duration-300 group-hover:-rotate-12"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"
                                    />
                                </svg>

                                <!-- Efecto de brillo -->
                                <div
                                    class="absolute inset-0 rounded-lg bg-gradient-to-br from-amber-100/50 to-transparent dark:from-indigo-900/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                                ></div>
                            </button>

                            <Dropdown align="right" width="48">
                                <!-- Trigger -->
                                <template #trigger>
                                    <div
                                        class="flex items-center gap-2 bg-white dark:bg-gray-800 rounded-fulls px-2 py-1 shadow border border-gray-200 dark:border-gray-700 cursor-pointer relative transition-colors duration-300"
                                    >
                                        <!-- Avatar con imagen o iniciales -->
                                        <div
                                            v-if="$page.props.auth.user.avatar"
                                            class="w-8 h-8 rounded-full overflow-hidden"
                                            style="border-radius: 100%"
                                        >
                                            <img
                                                :src="`/storage/${$page.props.auth.user.avatar}`"
                                                :alt="
                                                    $page.props.auth.user.name
                                                "
                                                class="w-full h-full object-cover"
                                                style="border-radius: 100%"
                                            />
                                        </div>
                                        <div
                                            v-else
                                            class="w-8 h-8 rounded-fulls bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-base uppercase shadow"
                                            style="border-radius: 100%"
                                        >
                                            {{
                                                $page.props.auth.user.name.charAt(
                                                    0
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="hidden sm:block text-gray-800 dark:text-gray-200 leading-tight max-w-[100px] overflow-hidden whitespace-nowrap truncate transition-colors duration-300"
                                        >
                                            <div
                                                class="font-semibold text-sm truncate"
                                            >
                                                {{ $page.props.auth.user.name }}
                                            </div>
                                            <div
                                                class="text-xs text-gray-500 truncate"
                                            >
                                                {{ $page.props.auth.role }}
                                            </div>
                                        </div>
                                        <!-- Flecha caret -->
                                        <svg
                                            class="w-4 h-4 text-gray-400 ml-2 mr-1"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </template>

                                <!-- Contenido del Dropdown -->
                                <template #content>
                                    <!-- Solo visible en mobile -->
                                    <div
                                        class="block lg:hidden px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 transition-colors duration-300"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div>
                                                <div
                                                    class="font-semibold text-gray-800 dark:text-gray-200 text-base transition-colors duration-300"
                                                >
                                                    {{
                                                        $page.props.auth.user
                                                            .name
                                                    }}
                                                </div>
                                                <div
                                                    v-if="$page.props.auth.role"
                                                    class="inline-block mt-1 px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 text-xs font-semibold"
                                                >
                                                    {{ $page.props.auth.role }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <DropdownLink :href="route('profile.edit')">
                                        <div class="flex items-center gap-3">
                                            <svg
                                                class="w-5 h-5 text-indigo-500"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.964 0a9 9 0 1 0-11.964 0m11.964 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                                />
                                            </svg>
                                            <span
                                                class="text-sm font-medium text-gray-700"
                                                >Mi Perfil</span
                                            >
                                        </div>
                                    </DropdownLink>

                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                    >
                                        <div class="flex items-center gap-3">
                                            <svg
                                                class="w-5 h-5 text-red-500"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"
                                                />
                                            </svg>
                                            <span
                                                class="text-sm font-medium text-gray-700"
                                                >Cerrar Sesión</span
                                            >
                                        </div>
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ===== PAGE CONTENT ===== -->
            <main
                class="flex-1 overflow-y-auto p-4 sm:p-3 lg:p-6 bg-gray-100 dark:bg-gray-900 transition-colors duration-300"
            >
                <div class="mx-auto rellenoInternas">
                    <slot name="header" />
                    <slot />
                </div>
            </main>
        </div>

        <!-- SweetAlert Component - Global -->
        <SweetAlert />

        <!-- Asistente conversacional con IA (Gemini) - Cap. 3 de la tesis -->
        <ChatIAWidget />
    </div>
</template>

<!-- =========================
    3. STYLE
========================= -->
<style scoped>
/* =========================
   TRANSICIONES SUAVES
========================= */
.fade-slide-enter-active {
    transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-leave-active {
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-enter-from {
    opacity: 0;
}

.fade-slide-leave-to {
    opacity: 0;
}

.fade-slide-enter-to,
.fade-slide-leave-from {
    opacity: 1;
}

/* =========================
   SIDEBAR BASE
========================= */
.bgPrincipal {
    background: linear-gradient(
        180deg,
        var(--colorPrincipal) 0%,
        color-mix(in srgb, var(--colorPrincipal) 90%, black) 100%
    );
    position: relative;
}

.bgPrincipal::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(
        ellipse at top,
        rgba(255, 255, 255, 0.1) 0%,
        transparent 60%
    );
    pointer-events: none;
}

/* =========================
   BOTONES Y ENLACES DEL MENÚ
========================= */
nav button,
nav a {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

nav button:hover,
nav a:hover {
    background-color: rgba(255, 255, 255, 0.12) !important;
}

nav button:active,
nav a:active {
    background-color: rgba(255, 255, 255, 0.08) !important;
}

/* Efecto de borde inferior al hover */
nav button:hover::after,
nav a:hover::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.4),
        transparent
    );
    animation: borderGlow 1.5s ease-in-out infinite;
}

@keyframes borderGlow {
    0%,
    100% {
        opacity: 0.5;
    }
    50% {
        opacity: 1;
    }
}

/* =========================
   SVG ICONS CON EFECTOS
========================= */
.svgMenu {
    display: inline-block;
    vertical-align: middle;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.15));
}

nav button:hover .svgMenu,
nav a:hover svg {
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25)) brightness(1.1);
}

/* Animación de pulso sutil para iconos activos */
.menuActivado .svgMenu {
    animation: iconPulse 3s ease-in-out infinite;
}

@keyframes iconPulse {
    0%,
    100% {
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }
    50% {
        filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.3)) brightness(1.05);
    }
}

/* =========================
   OVERLAYSCROLLBARS MEJORADO
========================= */
:deep(.os-scrollbar) {
    --os-size: 3.5px;
    --os-padding-perpendicular: 0px;
    --os-padding-axis: 0px;
    --os-track-border-radius: 10px;
    --os-track-bg: transparent;
    --os-track-bg-hover: rgba(255, 255, 255, 0.05);
    --os-track-bg-active: rgba(255, 255, 255, 0.08);
    --os-handle-border-radius: 10px;
    --os-handle-bg: rgba(255, 255, 255, 0.25);
    --os-handle-bg-hover: rgba(255, 255, 255, 0.4);
    --os-handle-bg-active: rgba(255, 255, 255, 0.5);
    --os-handle-min-size: 50px;
    --os-handle-max-size: none;
    --os-handle-perpendicular-size: 100%;
    --os-handle-perpendicular-size-hover: 100%;
    --os-handle-perpendicular-size-active: 100%;
}

:deep(.os-scrollbar-vertical) {
    right: 1px;
}

:deep(.os-scrollbar-handle) {
    transition: background-color 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

:deep(.os-scrollbar-handle:hover) {
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
}

:deep(.os-scrollbar-track) {
    transition: background-color 0.3s ease;
}

/* =========================
   ESTADOS ESPECIALES
========================= */

/* Botón de toggle menú con efecto */
button[class*='toggleSidebar'] {
    position: relative;
    overflow: hidden;
}

button[class*='toggleSidebar']::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    transform: translate(-50%, -50%);
    transition: width 0.5s ease, height 0.5s ease;
}

button[class*='toggleSidebar']:hover::before {
    width: 200%;
    height: 200%;
}

/* =========================
   MOBILE OVERLAY MEJORADO
========================= */
.fixed.inset-0.bg-black.bg-opacity-50 {
    backdrop-filter: blur(4px);
    transition: backdrop-filter 0.3s ease;
}

/* =========================
   RESPONSIVE
========================= */
@media (max-width: 1023px) {
    .group:hover .group-hover\:visible {
        visibility: hidden !important;
        opacity: 0 !important;
    }

    /* Ajustes para móvil */
    nav button:hover::after,
    nav a:hover::after {
        display: none;
    }
}

/* =========================
   TRANSICIÓN DEL SIDEBAR
========================= */
aside {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* =========================
   LOGO CON EFECTO
========================= */
.border-b.border-white\/10 {
    position: relative;
}

.border-b.border-white\/10::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 10%;
    right: 10%;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.3),
        transparent
    );
}

/* =========================
   PERFORMANCE
========================= */
* {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Optimización de GPU para transiciones */
nav button,
nav a,
.svgMenu,
:deep(.os-scrollbar-handle) {
    will-change: background-color, opacity, filter;
}

/* =========================
   TEXTO DEL MENÚ MODERNO
========================= */
.menu-text {
    font-size: 0.875rem; /* 14px - Tamaño estándar para interfaces modernas */
    font-weight: 400; /* Medium weight - equilibrio perfecto */
    letter-spacing: 0.015em; /* Espaciado moderado para legibilidad óptima */
    /* Sin text-transform - el texto se muestra tal como está escrito */
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
        'Helvetica Neue', Arial, sans-serif;
}

/* =========================
   MODO OSCURO - SIDEBAR Y UI
========================= */
/* El .bgPrincipal ahora usa var(--colorPrincipal) que se adapta automáticamente */

.dark .menuActivado {
    background: linear-gradient(
        90deg,
        color-mix(in srgb, var(--colorSecundario) 20%, transparent) 0%,
        color-mix(in srgb, var(--colorSecundario) 10%, transparent) 100%
    ) !important;
    border-left-color: color-mix(
        in srgb,
        var(--colorSecundario) 80%,
        transparent
    ) !important;
}

.dark .separador {
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.1) 10%,
        rgba(255, 255, 255, 0.2) 30%,
        rgba(255, 255, 255, 0.3) 50%,
        rgba(255, 255, 255, 0.2) 70%,
        rgba(255, 255, 255, 0.1) 90%,
        transparent 100%
    );
}
</style>
