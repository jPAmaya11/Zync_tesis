<script setup>
// Imports de Vue y utilidades
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

// Imports de componentes
import ProfileChip from '@/Components/Badges/ProfileChip.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import FakePasswordInput from '@/Components/Inputs/FakePasswordInput.vue';
import InputField from '@/Components/Inputs/InputField.vue';
import ModalFormTabs from '@/Components/Modals/ModalFormTabs.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import UltraTable from '@/Components/Tables/UltraTable.vue';
import Actions from '@/Components/Utilities/Actions.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Imports de iconos
import {
    ChevronDownIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';

// =======================
// PROPS Y DATOS REACTIVOS
// =======================

const page = usePage();

const users = ref(page.props.users);
const { roles } = page.props;
const departamentos = computed(() => page.props.departamentos || []);
const creadores = computed(() => page.props.creadores || []);

// Variables de paginación
const currentPage = ref(page.props.pagination?.current_page || 1);
const perPage = ref(page.props.pagination?.per_page || 20);
const paginationInfo = computed(() => page.props.pagination || null);

// =======================
// FILTRO INLINE
// =======================

const showFiltros = ref(true);

const filtros = reactive({
    search:       page.props.filtrosActivos?.search       || '',
    search_campo: page.props.filtrosActivos?.search_campo || '',
    role_id:      page.props.filtrosActivos?.role_id      || '',
    department:   page.props.filtrosActivos?.department   || '',
    active:       page.props.filtrosActivos?.active       || '',
    created_by:   page.props.filtrosActivos?.created_by   || '',
    date_from:    page.props.filtrosActivos?.date_from    || '',
    date_to:      page.props.filtrosActivos?.date_to      || '',
});

const rolSeleccionado = computed(() => roles.find(r => String(r.id) === String(filtros.role_id)) ?? null);
const deptSeleccionado = computed(() => filtros.department || null);
const creadorSeleccionado = computed(() => {
    const c = creadores.value.find(c => String(c.id) === String(filtros.created_by));
    return c ? c.name : null;
});

const campoBusquedaOpciones = [
    { value: '',                 label: 'Todos los campos' },
    { value: 'name',             label: 'Nombre' },
    { value: 'email',            label: 'Email' },
    { value: 'numero_documento', label: 'N° Documento' },
    { value: 'phone',            label: 'Teléfono' },
];
const campoSeleccionado = computed(() =>
    campoBusquedaOpciones.find(o => o.value === filtros.search_campo) ?? campoBusquedaOpciones[0]
);

const estadoOpciones = [
    { value: '',  label: 'Todos los estados' },
    { value: '1', label: 'Activo' },
    { value: '0', label: 'Inactivo' },
];
const estadoSeleccionado = computed(() =>
    estadoOpciones.find(o => o.value === filtros.active) ?? estadoOpciones[0]
);

const hayFiltrosActivos = computed(() =>
    !!(filtros.search || filtros.role_id || filtros.department ||
       filtros.active !== '' || filtros.created_by || filtros.date_from || filtros.date_to)
);

function aplicarFiltros() {
    const params = { page: 1, perPage: perPage.value };
    if (filtros.search)       { params.search = filtros.search; params.search_campo = filtros.search_campo; }
    if (filtros.role_id)      params.role_id    = filtros.role_id;
    if (filtros.department)   params.department = filtros.department;
    if (filtros.active !== '') params.active    = filtros.active;
    if (filtros.created_by)   params.created_by = filtros.created_by;
    if (filtros.date_from)    params.date_from = filtros.date_from;
    if (filtros.date_to)      params.date_to   = filtros.date_to;
    router.get(route('users.index'), params, { preserveState: true, preserveScroll: true });
}

function limpiarFiltros() {
    Object.assign(filtros, { search: '', search_campo: '', role_id: '', department: '', active: '', created_by: '', date_from: '', date_to: '' });
    router.get(route('users.index'), { page: 1, perPage: perPage.value }, { preserveState: true, preserveScroll: true });
}

// =======================
// VARIABLES DE ESTADO
// =======================

// Modal y formulario
const showModal = ref(false);
const usuarioEditar = ref(null);
const tabActiva = ref('basicos');

// Dropdown selections
const selectedRole = ref(null);
const selectedEstado = ref(null);

// Watchers para datos reactivos
watch(
    () => page.props.users,
    (newUsers) => {
        if (newUsers) users.value = newUsers;
    },
    { deep: true }
);

// Formulario reactivo
const usuarioForm = reactive({
    name: '',
    email: '',
    password: '',
    active: true,
    department: '',
    position: '',
    phone: '',
    numero_documento: '',
    roles: [],
});

function abrirModalAgregar() {
    Object.assign(usuarioForm, {
        name: '',
        email: '',
        password: '',
        active: true,
        department: '',
        position: '',
        phone: '',
        numero_documento: '',
        roles: [],
    });
    selectedRole.value = null;
    selectedEstado.value = null;
    usuarioEditar.value = null;
    tabActiva.value = 'basicos';
    showModal.value = true;
}

function abrirModalEditar(user) {
    usuarioEditar.value = user;
    const rolesUsuario = user.roles || [];

    Object.assign(usuarioForm, {
        name: user.name,
        email: user.email,
        password: '',
        active: !!user.active,
        department: user.department || '',
        position: user.position || '',
        phone: user.phone || '',
        numero_documento: user.numero_documento || '',
        roles: rolesUsuario,
    });

    selectedRole.value = rolesUsuario.length > 0 ? rolesUsuario[0] : null;
    selectedEstado.value = user.active ? 'Activo' : 'Inactivo';

    tabActiva.value = 'basicos';
    showModal.value = true;
}

function cerrarModal() {
    showModal.value = false;
    usuarioEditar.value = null;
    selectedRole.value = null;
    selectedEstado.value = null;
}

function handleSuccess() {
    cerrarModal();
}

function handleBeforeSubmit() {
    const accion = usuarioEditar.value ? 'Actualizando' : 'Creando';
    showToast(`${accion} usuario...`, 'info');
}

function handleGeneralError(errorMessage) {
    showError('Error de conexión', errorMessage);
}

function selectRole(role) {
    selectedRole.value = role;
    usuarioForm.roles = role ? [role] : [];
}

function selectEstado(estado) {
    selectedEstado.value = estado;
    usuarioForm.active = estado === 'Activo' ? true : false;
}

function eliminarUsuario(user) {
    showConfirm(
        '¿Eliminar usuario?',
        `¿Estás seguro de eliminar a «${user.name}»?`,
        {
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
        }
    ).then((result) => {
        if (result.isConfirmed) {
            showLoading('Eliminando usuario...', 'Por favor espera un momento');

            router.delete(route('users.destroy', user.id), {
                onSuccess: () => {},
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

const tabs = [
    { value: 'basicos', label: 'Información', icon: UserIcon },
];

function handlePageChange(newPage) {
    currentPage.value = newPage;
    router.get(
        route('users.index'),
        {
            page: newPage,
            perPage: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

function handlePerPageChange(newPerPage) {
    perPage.value = newPerPage;
    currentPage.value = 1;
    router.get(
        route('users.index'),
        {
            page: 1,
            perPage: newPerPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

const usuariosTableColumns = [
    { key: 'id', label: 'ID', align: 'center' },
    { key: 'profile', label: 'Perfil', align: 'left' },
    { key: 'email', label: 'Email', align: 'left' },
    { key: 'documento', label: 'Documento', align: 'left' },
    { key: 'roles', label: 'Rol', align: 'left' },
    { key: 'creador', label: 'Creado por', align: 'left' },
    { key: 'estado', label: 'Estado', align: 'center' },
    { key: 'actions', label: 'Acciones', align: 'center' },
];

</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout class="relleno">
        <template #header>
            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
            >
                <!-- Título y contador -->
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-bold tituloPag">
                        Gestionar Usuarios
                    </h2>
                    <div
                        class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-lg border border-gray-200"
                    >
                        <svg
                            class="w-5 h-5 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                        <span class="text-sm font-semibold text-gray-700">
                            {{ paginationInfo?.total || users.length }}
                        </span>
                        <span class="text-sm text-gray-500">
                            {{
                                (paginationInfo?.total || users.length) === 1
                                    ? 'usuario'
                                    : 'usuarios'
                            }}
                        </span>
                    </div>
                </div>

                <!-- Controles -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Botón Filtros -->
                    <button @click="showFiltros = !showFiltros"
                        class="relative flex items-center gap-2 px-4 py-2.5 rounded-lg border shadow-sm transition-all duration-200 h-[44px]"
                        :class="hayFiltrosActivos
                            ? 'bg-indigo-50 dark:bg-indigo-500/10 border-indigo-400 text-indigo-700 dark:text-indigo-300'
                            : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                        </svg>
                        <span class="text-sm font-medium">Filtros</span>
                        <span v-if="hayFiltrosActivos"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 text-[10px] font-bold bg-indigo-600 text-white rounded-full flex items-center justify-center">
                            ✓
                        </span>
                    </button>

                    <button
                        v-if="canDo('usuarios.crear')"
                        @click="abrirModalAgregar"
                        class="flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-lg shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nuevo Usuario</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <!-- ── Panel de filtros inline ── -->
            <div class="mb-6 rounded-xl border border-gray-200 dark:border-gray-700/60 bg-gray-50/80 dark:bg-gray-800/40">
                <!-- Cabecera del panel -->
                <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700/60">
                    <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Filtros</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Refina la lista de usuarios según los criterios disponibles.</p>
                </div>

                <!-- Cuerpo colapsable -->
                <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition-all duration-150 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="showFiltros" class="px-5 py-4 space-y-3">
                        <!-- Fila 1: Buscar + Rol + Departamento -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <!-- Buscar (ocupa 2 columnas) -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Buscar</label>
                                <div class="flex gap-2">
                                    <!-- Campo de búsqueda -->
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 whitespace-nowrap">
                                                <span>{{ campoSeleccionado.label }}</span>
                                                <ChevronDownIcon class="w-4 h-4 text-gray-400 shrink-0" />
                                            </button>
                                        </template>
                                        <template #content>
                                            <div class="py-1">
                                                <button type="button" v-for="op in campoBusquedaOpciones" :key="op.value"
                                                    @click="filtros.search_campo = op.value"
                                                    class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                    :class="filtros.search_campo === op.value ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                    {{ op.label }}
                                                </button>
                                            </div>
                                        </template>
                                    </Dropdown>
                                    <input v-model="filtros.search" type="text" :placeholder="`Buscar por ${campoSeleccionado.label.toLowerCase()}...`" @keydown.enter="aplicarFiltros"
                                        class="flex-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <!-- Rol (searchable dropdown) -->
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Rol</label>
                                <Dropdown align="left" width="full" :search="true" searchPlaceholder="Buscar rol...">
                                    <template #trigger>
                                        <button type="button" class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <span class="truncate" :class="!rolSeleccionado ? 'text-gray-400' : ''">
                                                {{ rolSeleccionado ? rolSeleccionado.name : 'Todos los roles' }}
                                            </span>
                                            <ChevronDownIcon class="w-4 h-4 text-gray-400 shrink-0" />
                                        </button>
                                    </template>
                                    <template #content="{ search }">
                                        <div class="max-h-56 overflow-y-auto py-1">
                                            <button type="button" @click="filtros.role_id = ''"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="!filtros.role_id ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                Todos los roles
                                            </button>
                                            <button type="button"
                                                v-for="r in roles.filter(r => !search || r.name.toLowerCase().includes(search.toLowerCase()))"
                                                :key="r.id" @click="filtros.role_id = r.id"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="String(filtros.role_id) === String(r.id) ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                {{ r.name }}
                                            </button>
                                            <p v-if="!roles.filter(r => !search || r.name.toLowerCase().includes(search.toLowerCase())).length" class="px-4 py-2 text-sm text-gray-400">Sin resultados</p>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                            <!-- Departamento (searchable dropdown) -->
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Departamento</label>
                                <Dropdown align="left" width="full" :search="true" searchPlaceholder="Buscar departamento...">
                                    <template #trigger>
                                        <button type="button" class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <span class="truncate" :class="!deptSeleccionado ? 'text-gray-400' : ''">
                                                {{ deptSeleccionado || 'Todos los departamentos' }}
                                            </span>
                                            <ChevronDownIcon class="w-4 h-4 text-gray-400 shrink-0" />
                                        </button>
                                    </template>
                                    <template #content="{ search }">
                                        <div class="max-h-56 overflow-y-auto py-1">
                                            <button type="button" @click="filtros.department = ''"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="!filtros.department ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                Todos los departamentos
                                            </button>
                                            <button type="button"
                                                v-for="d in departamentos.filter(d => !search || d.toLowerCase().includes(search.toLowerCase()))"
                                                :key="d" @click="filtros.department = d"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="filtros.department === d ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                {{ d }}
                                            </button>
                                            <p v-if="!departamentos.filter(d => !search || d.toLowerCase().includes(search.toLowerCase())).length" class="px-4 py-2 text-sm text-gray-400">Sin resultados</p>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Fila 2: Estado + Creado por + Fecha Registro Inicio + Fin -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Estado</label>
                                <Dropdown align="left" width="full">
                                    <template #trigger>
                                        <button type="button" class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <span :class="!filtros.active && filtros.active !== '0' ? 'text-gray-400' : ''">{{ estadoSeleccionado.label }}</span>
                                            <ChevronDownIcon class="w-4 h-4 text-gray-400 shrink-0" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="py-1">
                                            <button type="button" v-for="op in estadoOpciones" :key="op.value"
                                                @click="filtros.active = op.value"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="filtros.active === op.value ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                {{ op.label }}
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                            <!-- Creado por (searchable dropdown) -->
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Creado por</label>
                                <Dropdown align="left" width="full" :search="true" searchPlaceholder="Buscar creador...">
                                    <template #trigger>
                                        <button type="button" class="w-full flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <span class="truncate" :class="!creadorSeleccionado ? 'text-gray-400' : ''">
                                                {{ creadorSeleccionado || 'Todos los creadores' }}
                                            </span>
                                            <ChevronDownIcon class="w-4 h-4 text-gray-400 shrink-0" />
                                        </button>
                                    </template>
                                    <template #content="{ search }">
                                        <div class="max-h-56 overflow-y-auto py-1">
                                            <button type="button" @click="filtros.created_by = ''"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="!filtros.created_by ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                Todos los creadores
                                            </button>
                                            <button type="button"
                                                v-for="c in creadores.filter(c => !search || c.name.toLowerCase().includes(search.toLowerCase()))"
                                                :key="c.id" @click="filtros.created_by = c.id"
                                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                                                :class="String(filtros.created_by) === String(c.id) ? 'bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 font-medium' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                                {{ c.name }}
                                            </button>
                                            <p v-if="!creadores.length" class="px-4 py-2 text-sm text-gray-400">Aún no hay creadores registrados</p>
                                            <p v-else-if="!creadores.filter(c => !search || c.name.toLowerCase().includes(search.toLowerCase())).length" class="px-4 py-2 text-sm text-gray-400">Sin resultados</p>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Fecha Registro — Desde</label>
                                <input v-model="filtros.date_from" type="date" class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Fecha Registro — Hasta</label>
                                <input v-model="filtros.date_to" type="date" class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button @click="limpiarFiltros" :disabled="!hayFiltrosActivos"
                                class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                Limpiar
                            </button>
                            <button @click="aplicarFiltros"
                                class="flex items-center gap-1.5 px-5 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Aplicar
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Modal Usuario -->
            <ModalFormTabs
                :show="showModal"
                :title="usuarioEditar ? 'Editar Usuario' : 'Agregar Usuario'"
                :submitLabel="usuarioEditar ? 'Actualizar' : 'Registrar'"
                :form="usuarioForm"
                :endpoint="
                    usuarioEditar
                        ? route('users.update', usuarioEditar.id)
                        : route('users.store')
                "
                :method="usuarioEditar ? 'put' : 'post'"
                :transform="
                    (form) => ({
                        ...form,
                        roles: Array.isArray(form.roles)
                            ? form.roles.map((r) => r.id)
                            : [],
                    })
                "
                :tabs="tabs"
                :tabActiva="tabActiva"
                @update:tabActiva="tabActiva = $event"
                @close="cerrarModal"
                @submit="handleBeforeSubmit"
                @success="handleSuccess"
                @general-error="handleGeneralError"
            >
                <template #default="{ form, errors }">
                    <div v-if="tabActiva === 'basicos'">
                        <input
                            type="text"
                            name="fake_username"
                            autocomplete="username"
                            style="display: none"
                            placeholder="John Smith"
                        />
                        <input
                            type="password"
                            name="fake_password"
                            autocomplete="new-password"
                            style="display: none"
                            placeholder="Contraseña"
                        />

                        <InputField
                            class="modalInputs"
                            label="Nombre"
                            v-model="form.name"
                            :error="errors.name"
                            placeholder="John Smith"
                        />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="modalInputs">
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                >
                                    Rol
                                </label>
                                <Dropdown
                                    align="left"
                                    :search="true"
                                    searchPlaceholder="Buscar rol..."
                                >
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="modalSelect focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                                        >
                                            <span>
                                                {{
                                                    selectedRole
                                                        ? selectedRole.name
                                                        : 'Seleccionar rol'
                                                }}
                                            </span>
                                            <ChevronDownIcon
                                                class="h-4 w-4 text-gray-400"
                                            />
                                        </button>
                                    </template>
                                    <template #content="{ search }">
                                        <div class="max-h-60 overflow-y-auto">
                                            <button
                                                type="button"
                                                @click="
                                                    selectRole(null);
                                                    form.roles = [];
                                                "
                                                class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                                :class="{
                                                    'bg-indigo-50 text-indigo-600':
                                                        !selectedRole,
                                                }"
                                            >
                                                Seleccionar rol
                                            </button>
                                            <button
                                                type="button"
                                                v-for="role in roles.filter(
                                                    (r) =>
                                                        !search ||
                                                        r.name
                                                            .toLowerCase()
                                                            .includes(
                                                                search.toLowerCase()
                                                            ) ||
                                                        (r.description &&
                                                            r.description
                                                                .toLowerCase()
                                                                .includes(
                                                                    search.toLowerCase()
                                                                ))
                                                )"
                                                :key="role.id"
                                                @click="selectRole(role)"
                                                class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                                :class="{
                                                    'bg-indigo-50 text-indigo-600':
                                                        selectedRole?.id ===
                                                        role.id,
                                                }"
                                            >
                                                {{ role.name }}
                                                <span
                                                    v-if="role.description"
                                                    class="text-xs text-gray-500 block"
                                                >
                                                    {{ role.description }}
                                                </span>
                                            </button>
                                            <div
                                                v-if="
                                                    roles.filter(
                                                        (r) =>
                                                            !search ||
                                                            r.name
                                                                .toLowerCase()
                                                                .includes(
                                                                    search.toLowerCase()
                                                                ) ||
                                                            (r.description &&
                                                                r.description
                                                                    .toLowerCase()
                                                                    .includes(
                                                                        search.toLowerCase()
                                                                    ))
                                                    ).length === 0
                                                "
                                                class="px-4 py-2 text-sm text-gray-400"
                                            >
                                                No se encontraron roles
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                                <p
                                    v-if="errors.roles"
                                    class="text-red-600 text-sm"
                                >
                                    {{ errors.roles }}
                                </p>
                            </div>

                            <div class="modalInputs">
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                >
                                    Estado
                                </label>
                                <Dropdown align="left">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="modalSelect focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                                        >
                                            <span class="truncate">
                                                {{
                                                    selectedEstado
                                                        ? selectedEstado
                                                        : 'Seleccionar estado'
                                                }}
                                            </span>
                                            <ChevronDownIcon
                                                class="w-4 h-4 ml-1 text-gray-500"
                                            />
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="max-h-60 overflow-y-auto">
                                            <button
                                                type="button"
                                                @click="selectEstado('Activo')"
                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                            >
                                                Activo
                                            </button>
                                            <button
                                                type="button"
                                                @click="
                                                    selectEstado('Inactivo')
                                                "
                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                            >
                                                Inactivo
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <InputField
                                class="modalInputs"
                                label="Número de Documento"
                                v-model="form.numero_documento"
                                :error="errors.numero_documento"
                                placeholder="Ej: 12345678A, 123456789"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <InputField
                                class="modalInputs"
                                label="Email"
                                v-model="form.email"
                                :error="errors.email"
                                placeholder="john.smith@gmail.com"
                            />

                            <FakePasswordInput
                                class="modalInputs"
                                :label="
                                    usuarioEditar
                                        ? 'Nueva contraseña (opcional)'
                                        : 'Contraseña'
                                "
                                v-model="form.password"
                                placeholder="Deja en blanco para no cambiar"
                                :error="errors.password"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <InputField
                                class="modalInputs"
                                label="Departamento"
                                v-model="form.department"
                                :error="errors.department"
                                placeholder="Ej: Tecnología, Ventas, RRHH"
                            />
                            <InputField
                                class="modalInputs"
                                label="Cargo"
                                v-model="form.position"
                                :error="errors.position"
                                placeholder="Ej: Desarrollador, Gerente"
                            />
                        </div>

                        <InputField
                            class="modalInputs"
                            label="Teléfono"
                            v-model="form.phone"
                            :error="errors.phone"
                            placeholder="Ej: +51 999 888 777"
                        />

                        <!-- Fecha de creación (solo lectura, solo al editar) -->
                        <div v-if="usuarioEditar" class="modalInputs">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 mt-2">
                                Fecha de Creación
                            </label>
                            <div class="flex items-center gap-2 px-3 py-2 rounded-sm border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-sm text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>{{ usuarioEditar.created_at ? new Date(usuarioEditar.created_at).toLocaleDateString('es-PE', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }}</span>
                            </div>
                        </div>
                    </div>

                </template>
            </ModalFormTabs>

            <!-- Tabla usando UltraTable -->
            <UltraTable
                :columns="usuariosTableColumns"
                :rows="users"
                :modelValue="currentPage"
                :totalPages="paginationInfo?.last_page || 1"
                :useInternalPagination="false"
                :paginationInfo="paginationInfo"
                :currentPerPage="perPage"
                :showPerPageSelector="true"
                :showPagination="paginationInfo && paginationInfo.last_page > 1"
                empty-message="No hay usuarios registrados."
                container-class="border-0 shadow-none"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
            >
                <template #cell-id="{ value }">
                    <div class="flex items-center justify-center">
                        <span class="text-sm font-semibold text-gray-600">
                            {{ value }}
                        </span>
                    </div>
                </template>

                <template #cell-profile="{ item }">
                    <div class="flex items-center py-1">
                        <ProfileChip :user="item" />
                    </div>
                </template>

                <template #cell-email="{ value }">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                        {{ value }}
                    </span>
                </template>

                <template #cell-documento="{ item }">
                    <div v-if="item.numero_documento" class="text-sm font-medium text-gray-900">
                        {{ item.numero_documento }}
                    </div>
                    <span v-else class="text-gray-400 text-xs italic"
                        >Sin documento</span
                    >
                </template>

                <template #cell-roles="{ item }">
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="r in item.roles"
                            :key="r.id"
                            class="inline-flex items-center bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md text-xs font-medium border border-blue-100"
                        >
                            {{ r.name }}
                        </span>
                        <span
                            v-if="!item.roles.length"
                            class="text-gray-400 text-xs italic"
                            >Sin roles</span
                        >
                    </div>
                </template>

                <template #cell-creador="{ item }">
                    <span
                        v-if="item.creador"
                        class="text-sm font-medium text-gray-600 dark:text-gray-300"
                    >
                        {{ item.creador.name }}
                    </span>
                    <span v-else class="text-gray-400 text-xs italic">—</span>
                </template>

                <template #cell-estado="{ item }">
                    <div class="flex justify-center">
                        <StatusBadge :active="!!item.active" />
                    </div>
                </template>

                <template #cell-actions="{ item }">
                    <Actions
                        :edit="canDo('usuarios.editar')"
                        :remove="canDo('usuarios.eliminar')"
                        @edit="abrirModalEditar(item)"
                        @delete="eliminarUsuario(item)"
                    />
                </template>
            </UltraTable>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.table-container-custom {
    position: relative;
}
</style>
