<template>
    <Head title="Mi Perfil" />
    <AuthenticatedLayout class="relleno">
        <template #header>
            <h2 class="text-xl font-semibold tituloPag">Mi Perfil</h2>
        </template>

        <div class="py-8">
            <div class="flex flex-col lg:flex-row gap-6 profile-container">
                <!-- Sidebar izquierdo con ProfileSidebar -->
                <ProfileSidebar
                    :user="user"
                    :show-avatar-upload="true"
                    :show-profile-status="true"
                    :avatar-form="avatarForm"
                    @avatar-click="$refs.avatarInput.click()"
                >
                </ProfileSidebar>

                <!-- Input oculto para avatar -->
                <input
                    ref="avatarInput"
                    type="file"
                    accept="image/*"
                    @change="handleAvatarChange"
                    class="hidden"
                />
                <!-- Contenido principal -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <!-- Navegación de tabs -->
                    <div class="mb-2">
                        <TabNav
                            v-model="activeTab"
                            :tabs="[
                                {
                                    key: 'personal',
                                    label: 'Información Personal',
                                },
                                { key: 'security', label: 'Seguridad' },
                                { key: 'activity', label: 'Actividad' },
                            ]"
                        >
                            <template #icon-personal>
                                <svg
                                    class="w-4 h-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="12" cy="8" r="4" />
                                    <path
                                        d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"
                                    />
                                </svg>
                            </template>
                            <template #icon-security>
                                <svg
                                    class="w-4 h-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M12 2L4 5v6c0 5.25 3.5 9.5 8 11 4.5-1.5 8-5.75 8-11V5l-8-3z"
                                    />
                                    <path d="M9 12l2 2 4-4" />
                                </svg>
                            </template>
                            <template #icon-activity>
                                <svg
                                    class="w-4 h-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 7v5l3.5 2" />
                                </svg>
                            </template>
                        </TabNav>
                    </div>

                    <!-- Contenido de tabs -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex-1 overflow-y-auto"
                    >
                        <div class="p-6 min-h-[500px]">
                            <!-- Tab: Información Personal -->
                            <div
                                v-if="activeTab === 'personal'"
                                class="space-y-6"
                            >
                                <form
                                    @submit.prevent="updateProfile"
                                    class="space-y-6"
                                >
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                    >
                                        <div>
                                            <label
                                                for="name"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Nombre completo *
                                            </label>
                                            <InputField
                                                id="name"
                                                v-model="form.name"
                                                type="text"
                                                required
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        form.errors.name,
                                                }"
                                            />
                                            <p
                                                v-if="form.errors.name"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.name }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="email"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Email *
                                            </label>
                                            <InputField
                                                id="email"
                                                v-model="form.email"
                                                type="email"
                                                required
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        form.errors.email,
                                                }"
                                            />
                                            <p
                                                v-if="form.errors.email"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.email }}
                                            </p>
                                            <p
                                                v-if="
                                                    !user.email_verified_at &&
                                                    mustVerifyEmail
                                                "
                                                class="mt-1 text-sm text-amber-600"
                                            >
                                                Tu email no está verificado.
                                                <Link
                                                    :href="
                                                        route(
                                                            'verification.send'
                                                        )
                                                    "
                                                    method="post"
                                                    class="underline hover:no-underline"
                                                >
                                                    Reenviar verificación
                                                </Link>
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="numero_documento"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Número de Documento
                                            </label>
                                            <InputField
                                                id="numero_documento"
                                                v-model="form.numero_documento"
                                                type="text"
                                                maxlength="255"
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        form.errors
                                                            .numero_documento,
                                                }"
                                                placeholder="Ej: 12345678A, 123456789"
                                            />
                                            <p
                                                v-if="
                                                    form.errors.numero_documento
                                                "
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{
                                                    form.errors.numero_documento
                                                }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="phone"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Teléfono
                                            </label>
                                            <InputField
                                                id="phone"
                                                v-model="form.phone"
                                                type="tel"
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        form.errors.phone,
                                                }"
                                                placeholder="Ej: +34 600 123 456"
                                            />
                                            <p
                                                v-if="form.errors.phone"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.phone }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="position"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Cargo/Posición
                                            </label>
                                            <InputField
                                                id="position"
                                                v-model="form.position"
                                                type="text"
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        form.errors.position,
                                                }"
                                                placeholder="Ej: Desarrollador Senior"
                                            />
                                            <p
                                                v-if="form.errors.position"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.position }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                for="department"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Departamento
                                            </label>
                                            <InputField
                                                id="department"
                                                v-model="form.department"
                                                type="text"
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        form.errors.department,
                                                }"
                                                placeholder="Ej: Desarrollo de Software"
                                            />
                                            <p
                                                v-if="form.errors.department"
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{ form.errors.department }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button
                                            type="submit"
                                            :disabled="form.processing"
                                            class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <span v-if="form.processing"
                                                >Guardando...</span
                                            >
                                            <span v-else>Guardar cambios</span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Tab: Seguridad -->
                            <div
                                v-if="activeTab === 'security'"
                                class="space-y-6"
                            >
                                <!-- Cambiar contraseña -->
                                <div
                                    class="bg-white border border-gray-200 rounded-lg p-6"
                                >
                                    <h3
                                        class="text-lg font-medium text-gray-900 mb-4"
                                    >
                                        Cambiar Contraseña
                                    </h3>

                                    <form
                                        @submit.prevent="updatePassword"
                                        class="space-y-4"
                                    >
                                        <div>
                                            <label
                                                for="current_password"
                                                class="block text-sm font-medium text-gray-700 mb-2"
                                            >
                                                Contraseña actual *
                                            </label>
                                            <InputField
                                                id="current_password"
                                                v-model="
                                                    passwordForm.current_password
                                                "
                                                type="password"
                                                required
                                                :spacing="false"
                                                class="modalInputs"
                                                :class="{
                                                    'border-red-500':
                                                        passwordForm.errors
                                                            .current_password,
                                                }"
                                            />
                                            <p
                                                v-if="
                                                    passwordForm.errors
                                                        .current_password
                                                "
                                                class="mt-1 text-sm text-red-600"
                                            >
                                                {{
                                                    passwordForm.errors
                                                        .current_password
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                        >
                                            <div>
                                                <label
                                                    for="password"
                                                    class="block text-sm font-medium text-gray-700 mb-2"
                                                >
                                                    Nueva contraseña *
                                                </label>
                                                <InputField
                                                    id="password"
                                                    v-model="
                                                        passwordForm.password
                                                    "
                                                    type="password"
                                                    required
                                                    :spacing="false"
                                                    class="modalInputs"
                                                    :class="{
                                                        'border-red-500':
                                                            passwordForm.errors
                                                                .password,
                                                    }"
                                                />
                                                <p
                                                    v-if="
                                                        passwordForm.errors
                                                            .password
                                                    "
                                                    class="mt-1 text-sm text-red-600"
                                                >
                                                    {{
                                                        passwordForm.errors
                                                            .password
                                                    }}
                                                </p>
                                            </div>

                                            <div>
                                                <label
                                                    for="password_confirmation"
                                                    class="block text-sm font-medium text-gray-700 mb-2"
                                                >
                                                    Confirmar contraseña *
                                                </label>
                                                <InputField
                                                    id="password_confirmation"
                                                    v-model="
                                                        passwordForm.password_confirmation
                                                    "
                                                    type="password"
                                                    required
                                                    :spacing="false"
                                                    class="modalInputs"
                                                />
                                            </div>
                                        </div>

                                        <div class="flex justify-end">
                                            <button
                                                type="submit"
                                                :disabled="
                                                    passwordForm.processing
                                                "
                                                class="px-6 py-2 bg-red-600 text-white font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <span
                                                    v-if="
                                                        passwordForm.processing
                                                    "
                                                    >Actualizando...</span
                                                >
                                                <span v-else
                                                    >Cambiar contraseña</span
                                                >
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tab: Actividad -->
                            <div
                                v-if="activeTab === 'activity'"
                                class="space-y-6"
                            >
                                <div class="text-center py-12">
                                    <svg
                                        class="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                        />
                                    </svg>
                                    <h3
                                        class="mt-2 text-sm font-medium text-gray-900"
                                    >
                                        Actividad reciente
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Aquí aparecerá tu actividad reciente en
                                        el sistema.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import InputField from '@/Components/Inputs/InputField.vue';
import ProfileSidebar from '@/Components/Layout/ProfileSidebar.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import TabNav from '@/Components/Navigation/TabNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: Boolean,
    user: Object,
});

const activeTab = ref('personal');

// Formulario principal
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    numero_documento: props.user.numero_documento || '',
    phone: props.user.phone || '',
    phone_adicional: props.user.phone_adicional || '',
    position: props.user.position || '',
    department: props.user.department || '',
    avatar: null,
});

// Formulario de contraseña
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Formulario específico para avatar
const avatarForm = useForm({
    avatar: null,
});

function handleAvatarChange(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alert('El archivo es demasiado grande. Máximo 2MB.');
            return;
        }

        if (!file.type.startsWith('image/')) {
            alert('Solo se permiten archivos de imagen.');
            return;
        }

        // Usar el formulario específico para avatar
        avatarForm.avatar = file;

        avatarForm.post(route('profile.avatar.update'), {
            forceFormData: true,
            onSuccess: () => {
                // Resetear el formulario de avatar
                avatarForm.reset();
                // Limpiar el input file
                event.target.value = '';
                // Recargar la página para mostrar la nueva imagen
                window.location.reload();
            },
            onError: (errors) => {
                console.error('Error al subir avatar:', errors);
                if (errors.avatar) {
                    alert('Error: ' + errors.avatar);
                }
            },
        });
    }
}

function updateProfile() {
    form.patch(route('profile.update'), {
        onSuccess: () => {
            // Mostrar mensaje de éxito si es necesario
            console.log('Perfil actualizado correctamente');
        },
        onError: (errors) => {
            console.error('Errores de validación:', errors);
        },
    });
}

function updatePassword() {
    passwordForm.put(route('profile.password.update'), {
        onSuccess: () => {
            passwordForm.reset();
        },
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function calculateDaysActive() {
    const createdDate = new Date(props.user.created_at);
    const currentDate = new Date();
    const diffTime = Math.abs(currentDate - createdDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
}
</script>

<style scoped>
/* Contenedor principal con altura dinámica que se adapta al viewport */
.profile-container {
    height: calc(100vh - 250px);
    min-height: 600px;
    max-height: none;
}

/* Estilos para el container mobile */
@media (max-width: 768px) {
    .profile-container {
        height: 100%;
    }
}

/* Scroll personalizado para el área de información del perfil */
.profile-info-scroll {
    overflow-y: auto;
    max-height: calc(100vh - 500px);
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

/* Animaciones suaves para elementos interactivos */
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

/* Estilos responsivos mejorados */
@media (max-width: 1024px) {
    .profile-info-scroll {
        max-height: none;
    }
}

/* Animación de entrada suave para los elementos */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-info-scroll > div {
    animation: fadeInUp 0.6s ease-out forwards;
}

.profile-info-scroll > div:nth-child(1) {
    animation-delay: 0.1s;
}

.profile-info-scroll > div:nth-child(2) {
    animation-delay: 0.2s;
}

.profile-info-scroll > div:nth-child(3) {
    animation-delay: 0.3s;
}

.profile-info-scroll > div:nth-child(4) {
    animation-delay: 0.4s;
}

/* ========================
   MODO OSCURO - PERFIL
   ======================== */
/* Labels del formulario */
:deep(.dark) label.text-gray-700 {
    color: rgb(209, 213, 219) !important;
}

/* Textos descriptivos */
:deep(.dark) .text-gray-600 {
    color: rgb(156, 163, 175) !important;
}

:deep(.dark) .text-gray-500 {
    color: rgb(107, 114, 128) !important;
}

/* Cards y contenedores de secciones */
:deep(.dark) .bg-gray-50 {
    background-color: rgb(31, 41, 55) !important;
}

/* Bordes de inputs y selects */
:deep(.dark) .border-gray-300 {
    border-color: rgb(75, 85, 99) !important;
}

/* Dropdown trigger buttons */
:deep(.dark) button.bg-white {
    background-color: rgb(31, 41, 55) !important;
    color: rgb(243, 244, 246) !important;
    border-color: rgb(75, 85, 99) !important;
}

:deep(.dark) button.bg-white:hover {
    background-color: rgb(55, 65, 81) !important;
}

/* Textos de ayuda y descripciones */
:deep(.dark) .text-sm.text-gray-600 {
    color: rgb(156, 163, 175) !important;
}

/* Secciones de configuración */
:deep(.dark) .border-gray-200 {
    border-color: rgb(55, 65, 81) !important;
}
</style>
