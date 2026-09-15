<script setup>
import PermissionTree from '@/Components/Accordions/PermissionTree.vue';
import InputField from '@/Components/Inputs/InputField.vue';
import ModalFormTabs from '@/Components/Modals/ModalFormTabs.vue';
import Actions from '@/Components/Utilities/Actions.vue';
import RoleUsersModal from '@/Components/Modules/Role/RoleUsersModal.vue';
import RoleAuditModal from '@/Components/Modules/Role/RoleAuditModal.vue';
import ModuleCommentModal from '@/Components/Modules/Role/ModuleCommentModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

// Props y permisos
const page = usePage();
const roles = ref(page.props.roles);
const { permissions } = page.props;
const search = ref(page.props.search || '');

// Descripciones por módulo de permisos (mapa module_id => { body, editor, updated_at }).
// Reactivo local para reflejar altas/ediciones/borrados sin recargar la página.
const moduleComments = ref({ ...(page.props.moduleComments || {}) });

// Modal "Ver usuarios del rol"
const showUsersModal = ref(false);
const roleForUsers = ref(null);
function abrirUsuariosRol(role) {
    roleForUsers.value = role;
    showUsersModal.value = true;
}

// Auditoría (solo rol admin). El backend además exige admin (abort_unless).
const showAuditModal = ref(false);
// Scope de la auditoría: null = vista UNIFICADA (botón superior); un rol = solo ese rol (ojo).
const auditRoleId = ref(null);
const auditRoleName = ref('');
function abrirAuditoria(role = null) {
    auditRoleId.value = role?.id ?? null;
    auditRoleName.value = role?.name ?? '';
    showAuditModal.value = true;
}
const esAdmin = computed(() => {
    const a = page.props.auth || {};
    if (a.role === 'admin') return true;
    return (a.user?.roles || []).some(r => (r?.name ?? r) === 'admin');
});

// Modal y formulario
const showModal = ref(false);
const rolEditar = ref(null);
const rolForm = reactive({
    name: '',
    permissions: [],
});

// Watchers para datos reactivos
watch(
    () => page.props.roles,
    (newRoles) => {
        if (newRoles) roles.value = newRoles;
    },
    { deep: true }
);

// Función para realizar la búsqueda
function performSearch() {
    router.visit(route('roles.index'), {
        data: { search: search.value },
        preserveState: true,
        preserveScroll: true,
    });
}

// Agrupar permisos por módulo
const permisosPorModulo = computed(() => {
    const agrupados = {};
    for (const p of permissions) {
        const mod = p.module?.name ?? 'Sin módulo';
        if (!agrupados[mod]) agrupados[mod] = [];
        agrupados[mod].push(p);
    }

    return Object.entries(agrupados).map(([nombre, permisos], index) => ({
        id: index,
        // module_id real del grupo (para asociar la descripción). null si es "Sin módulo".
        moduleId: permisos[0]?.module?.id ?? null,
        nombre,
        permisos,
    }));
});

// ── Modal de descripción por módulo de permisos ──────────
const showCommentModal = ref(false);
const commentModule = ref(null); // { id, nombre }

function abrirComentario({ moduleId, nombre }) {
    commentModule.value = { id: moduleId, nombre };
    showCommentModal.value = true;
}

// Descripción actual del módulo abierto (o null si aún no tiene).
const commentActual = computed(() =>
    commentModule.value ? (moduleComments.value[commentModule.value.id] ?? null) : null
);

function onCommentSaved(comment) {
    if (comment && comment.module_id != null) {
        moduleComments.value = { ...moduleComments.value, [comment.module_id]: comment };
    }
}

function onCommentDeleted(moduleId) {
    const copy = { ...moduleComments.value };
    delete copy[moduleId];
    moduleComments.value = copy;
}

// Función para extraer la acción del permiso
function extraerAccionPermiso(permiso) {
    const parts = permiso.name.split('.');
    const accion = parts[parts.length - 1];
    return accion
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

// Función para obtener el submódulo (retorna null si no tiene)
function getSubmoduloPermiso(permiso) {
    const parts = permiso.name.split('.');
    return parts.length > 2 ? parts[1] : null;
}

// Configuración de tabs
const tabActiva = ref('basico');

const tabs = [
    { value: 'basico', label: 'Datos Básicos', icon: 'UserGroupIcon' },
    { value: 'avanzado', label: 'Permisos Avanzados', icon: 'CogIcon' },
];

// Función para transformar form data
function transformarForm(form) {
    return { ...form };
}

// Lifecycle
onMounted(() => {
    // Habilitar scroll horizontal con la rueda del mouse
    document.querySelectorAll('.scrollbar-ghost').forEach((el) => {
        el.addEventListener('wheel', function (e) {
            if (e.deltaY !== 0) {
                e.preventDefault();
                el.scrollBy({
                    left: e.deltaY,
                    behavior: 'smooth',
                });
            }
        });
    });
});

// Funciones para modales
function abrirModalAgregar() {
    Object.assign(rolForm, {
        name: '',
        permissions: [],
    });
    rolEditar.value = null;
    showModal.value = true;
}

function abrirModalEditar(role) {
    rolEditar.value = role;

    Object.assign(rolForm, {
        name: role.name,
        permissions: role.permissions.map((p) => p.name),
    });

    showModal.value = true;
}

function cerrarModal() {
    showModal.value = false;
    rolEditar.value = null;
}

function handleSuccess() {
    cerrarModal();
}

function handleBeforeSubmit() {
    const accion = rolEditar.value ? 'Actualizando' : 'Creando';
    showToast(`${accion} rol...`, 'info');
}

function handleGeneralError(errorMessage) {
    showError('Error de conexión', errorMessage);
}

function eliminarRol(role) {
    showConfirm(
        '¿Eliminar rol?',
        `¿Estás seguro de eliminar el rol «${role.name}»? Esta acción no se puede deshacer.`,
        {
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then((result) => {
        if (result.isConfirmed) {
            showLoading('Eliminando rol...', 'Por favor espera un momento');

            router.delete(route('roles.destroy', role.id), {
                onSuccess: () => {
                    // El backend maneja el redirect automáticamente
                },
                onError: () => {
                    hideLoading();
                    showError(
                        'Error al eliminar',
                        'No se pudo conectar con el servidor. Inténtalo de nuevo.'
                    );
                },
            });
        }
    });
}
</script>

<template>
    <Head title="Roles" />
    <AuthenticatedLayout class="relleno">
        <template #header>
            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
            >
                <!-- Título y contador -->
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-bold tituloPag">
                        Gestionar Roles
                    </h2>
                    <div
                        class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600"
                    >
                        <svg
                            class="w-5 h-5 text-gray-400 dark:text-gray-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                        >
                            {{ roles.length }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ roles.length === 1 ? 'rol' : 'roles' }}
                        </span>
                    </div>
                </div>

                <!-- Botón agregar -->
                <div class="flex items-center gap-3">
                    <!-- Campo de búsqueda -->
                    <div class="relative">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar roles..."
                            @keydown.enter="performSearch"
                            class="pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200 w-64"
                        />
                        <svg
                            class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 dark:text-gray-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </div>
                    <!-- Auditoría: solo el rol admin -->
                    <button
                        v-if="esAdmin"
                        @click="abrirAuditoria(null)"
                        title="Historial de movimientos del módulo de roles"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 h-[44px]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-sm font-medium">Auditoría</span>
                    </button>

                    <button
                        v-if="canDo('roles.crear')"
                        @click="abrirModalAgregar"
                        class="flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-lg shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px]"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        <span>Nuevo Rol</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto w-full">
                <!-- Modal para agregar/editar rol -->
                <ModalFormTabs
                    :show="showModal"
                    :title="rolEditar ? 'Editar Rol' : 'Agregar Rol'"
                    :submitLabel="rolEditar ? 'Actualizar' : 'Registrar'"
                    :form="rolForm"
                    :endpoint="
                        rolEditar
                            ? route('roles.update', rolEditar.id)
                            : route('roles.store')
                    "
                    :method="rolEditar ? 'put' : 'post'"
                    :transform="transformarForm"
                    @close="cerrarModal"
                    @submit="handleBeforeSubmit"
                    @success="handleSuccess"
                    @general-error="handleGeneralError"
                    v-model:tabActiva="tabActiva"
                    :tabs="tabs"
                >
                    <template #default="{ form, errors }">
                        <div v-if="tabActiva === 'basico'" class="space-y-4">
                            <InputField
                                class="modalInputs"
                                label="Nombre del rol"
                                v-model="form.name"
                                placeholder="role name"
                                :error="errors.name"
                            />

                        </div>

                        <div v-if="tabActiva === 'avanzado'" class="space-y-4">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >Permisos</label
                            >
                            <PermissionTree
                                v-model="form.permissions"
                                :groups="permisosPorModulo"
                                :module-comments="moduleComments"
                                :can-comment="esAdmin"
                                @open-comment="abrirComentario"
                            />
                            <p
                                v-if="errors.permissions"
                                class="text-red-600 dark:text-red-400 text-sm"
                            >
                                {{ errors.permissions }}
                            </p>
                        </div>
                    </template>
                </ModalFormTabs>

                <!-- Listado de roles -->
                <div>
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 cursor-auto"
                    >
                        <div
                            v-for="role in roles"
                            :key="role.id"
                            class="bg-white dark:bg-gray-800 rounded-xl border transition-all duration-200 shadow-sm w-full max-w-full border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md flex flex-col overflow-hidden"
                        >
                            <!-- Header simplificado -->
                            <div
                                class="p-2 border-b bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700 flex items-center gap-3 min-h-0 bgPrincipal cabeRoles"
                            >
                                <!-- Avatar con icono -->
                                <div
                                    class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center shadow-md flex-shrink-0"
                                >
                                    <svg
                                        class="w-5 h-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                </div>
                                <!-- Nombre del rol -->
                                <div class="min-w-0 flex-1">
                                    <h3
                                        class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate"
                                        :title="role.name"
                                    >
                                        {{ role.name }}
                                    </h3>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                    >
                                        ID: {{ role.id }}
                                    </p>
                                </div>
                            </div>

                            <!-- Cuerpo de la tarjeta -->
                            <div
                                class="p-4 flex-1 flex flex-col space-y-3 min-h-0"
                            >
                                <!-- Permisos -->
                                <div>
                                    <h4
                                        class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 truncate"
                                    >
                                        Permisos asignados
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-2 py-1 rounded-md text-xs font-medium border border-green-100 dark:border-green-800"
                                        >
                                            {{
                                                role.permissions
                                                    ? role.permissions.length
                                                    : 0
                                            }}
                                            permisos
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div
                                class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between min-h-0"
                            >
                                <div
                                    class="text-xs text-gray-500 dark:text-gray-400 min-w-0 flex-1 mr-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <svg
                                            class="w-3 h-3 text-gray-400 dark:text-gray-500 flex-shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                            />
                                        </svg>
                                        <span class="truncate">
                                            {{
                                                role.permissions &&
                                                role.permissions.length
                                                    ? role.permissions.length
                                                    : 0
                                            }}
                                            permisos
                                        </span>
                                    </div>
                                </div>

                                <div class="flex-shrink-0">
                                    <!-- Auditoría de ESTE rol (solo admin, botón "view" azul del componente Actions):
                                         mismo modal, scope acotado al rol. -->
                                    <Actions
                                        :view="esAdmin"
                                        view-title="Auditoría de este rol"
                                        :edit="canDo('roles.editar')"
                                        :remove="canDo('roles.eliminar')"
                                        @view="abrirAuditoria(role)"
                                        @edit="abrirModalEditar(role)"
                                        @delete="eliminarRol(role)"
                                    />
                                </div>
                            </div>

                            <!-- Botón: ver usuarios que tienen este rol -->
                            <button
                                type="button"
                                @click="abrirUsuariosRol(role)"
                                class="w-full px-4 py-2.5 flex items-center justify-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50/40 dark:bg-indigo-500/5 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 border-t border-gray-100 dark:border-gray-700 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />
                                </svg>
                                Ver usuarios ({{ role.users_count ?? 0 }})
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: usuarios que tienen el rol -->
        <RoleUsersModal
            :show="showUsersModal"
            :role="roleForUsers"
            @close="showUsersModal = false"
        />

        <!-- Modal: auditoría del módulo de roles (solo admin) -->
        <RoleAuditModal
            :show="showAuditModal"
            :role-id="auditRoleId"
            :role-name="auditRoleName"
            @close="showAuditModal = false"
        />

        <!-- Modal: descripción de un grupo de permisos (lee cualquiera; escribe solo admin) -->
        <ModuleCommentModal
            :show="showCommentModal"
            :module="commentModule"
            :comment="commentActual"
            :can-write="esAdmin"
            @close="showCommentModal = false"
            @saved="onCommentSaved"
            @deleted="onCommentDeleted"
        />
    </AuthenticatedLayout>
</template>
<style scoped>
/* width */
.scrollbar-ghost::-webkit-scrollbar {
    width: 4px !important;
    height: 3px !important;
}

/* Track */
.scrollbar-ghost::-webkit-scrollbar-track {
    border-radius: 10px;
    background: transparent;
}
.scrollbar-ghost:hover::-webkit-scrollbar-track {
    box-shadow: inset 0 0 5px #7a7a7a;
}

/* Handle */
.scrollbar-ghost::-webkit-scrollbar-thumb {
    border-radius: 10px;
    background: transparent;
    transition: background 0.4s;
    animation: thumbPulse 2s ease-in-out infinite alternate;
}

/* Hover más intenso */
.scrollbar-ghost:hover::-webkit-scrollbar-thumb {
    background: rgba(6, 125, 190, 0.8);
}

/* Definimos la animación */
@keyframes thumbPulse {
    from {
        background: rgba(255, 255, 255, 0.1);
    }
    to {
        background: rgba(6, 125, 190, 0.3);
    }
}
</style>
