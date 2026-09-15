<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        :title="modalTitle"
        size="4xl"
        :hide-footer="true"
        min-height="h-[70vh]"
        @close="close"
    >
        <template #subtitle>
            Espacio
            <span class="font-mono font-semibold text-white/90">{{ projectKey }}</span>
            <span class="opacity-70">·</span>
            {{ members.length }} miembro{{ members.length !== 1 ? 's' : '' }}
        </template>

        <!-- Layout 2 columnas. -mx-8/-mb-6 = full-bleed lateral e inferior; arriba se
             conserva el padding del modal para que no quede pegado al header. -->
        <div class="-mx-8 -mb-6 flex flex-col md:flex-row h-[calc(70vh-1.5rem)] overflow-hidden border-t border-gray-100 dark:border-gray-800">
            <!-- ═══ COLUMNA IZQ: LISTA DE MIEMBROS ═══ -->
            <aside class="w-full md:w-80 shrink-0 border-r border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30 flex flex-col overflow-hidden">
                <!-- Header con buscador (el acceso se da metiendo a la persona en un equipo) -->
                <div class="shrink-0 px-4 py-3 border-b border-gray-100 dark:border-gray-800 space-y-2">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                        </svg>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar miembro..."
                            class="w-full h-9 pl-8 pr-2 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        />
                    </div>
                </div>

                <!-- Lista scrolleable, agrupada por equipo -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-2">
                    <div v-if="!groupedMembers.length" class="py-10 text-center text-gray-400">
                        <p class="text-xs">{{ search ? 'Sin coincidencias' : 'No hay miembros asignados' }}</p>
                    </div>

                    <div v-for="g in groupedMembers" :key="g.key" class="space-y-1">
                        <!-- Encabezado de grupo (colapsable) -->
                        <button
                            type="button"
                            @click="toggleGroup(g.key)"
                            class="w-full flex items-center justify-between px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        >
                            <span class="flex items-center gap-1.5 min-w-0">
                                <svg :class="['w-3 h-3 shrink-0 transition-transform', collapsed[g.key] ? '-rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                                <span class="truncate">{{ g.label }}</span>
                            </span>
                            <span class="shrink-0 ml-2 text-gray-400 dark:text-gray-500 font-semibold">{{ g.members.length }}</span>
                        </button>

                        <!-- Miembros del grupo -->
                        <template v-if="!collapsed[g.key]">
                            <button
                                v-for="m in g.members"
                                :key="g.key + '-' + m.id"
                                type="button"
                                @click="selectMember(m)"
                                :class="[
                                    'w-full text-left px-3 py-1.5 rounded-lg border transition-all group relative',
                                    selectedId === m.id
                                        ? 'bg-indigo-500/10 border-indigo-200 dark:border-indigo-700 ring-1 ring-indigo-500/20'
                                        : 'border-transparent hover:bg-white dark:hover:bg-gray-800 hover:border-gray-200 dark:hover:border-gray-700',
                                    m.suspended ? 'opacity-60' : '',
                                ]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <UserAvatar :src="m.user?.avatar_url" :name="m.user?.display_name" size="md" />
                                    <div class="flex-1 min-w-0">
                                        <p :class="['text-sm font-semibold truncate', selectedId === m.id ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-800 dark:text-gray-200']">
                                            {{ m.user?.display_name }}
                                        </p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span :class="['text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md border', getRoleStyle(m.role)]">
                                                {{ m.role }}
                                            </span>
                                            <!-- Acceso suspendido por equipo inactivo (reversible al reactivarlo) -->
                                            <span
                                                v-if="m.suspended"
                                                class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                                title="Acceso suspendido: entró por un equipo que fue desactivado. Se restaura al reactivar el equipo."
                                            >Suspendido</span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </aside>

            <!-- ═══ COLUMNA DER: DETALLE / EDICIÓN ═══ -->
            <section class="flex-1 flex flex-col min-w-0 overflow-hidden bg-white dark:bg-gray-900/40">
                <!-- Empty State -->
                <div v-if="!showForm && !selectedId" class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center mb-4 text-indigo-500">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">Gestión de Roles</h3>
                    <p class="text-xs text-gray-500 mt-1 max-w-xs">Selecciona un miembro para editar su rol. El acceso al espacio se da metiendo a la persona en un equipo.</p>
                </div>

                <!-- Formulario Añadir / Editar -->
                <div v-else class="flex-1 flex flex-col overflow-hidden">
                    <div class="shrink-0 px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ showForm ? 'Añadir nuevo miembro' : 'Editar rol de miembro' }}
                        </h3>
                    </div>

                    <div class="flex-1 overflow-y-auto p-5">
                        <!-- Seleccionar Usuario (Solo en añadir) -->
                        <div v-if="showForm" class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Seleccionar Usuario</label>
                            <SmartSelect
                                v-model="form.user_id"
                                :options="availableUserOptions"
                                placeholder="Buscar por nombre o correo..."
                                searchable
                            />
                        </div>

                        <!-- Selector de Rol -->
                        <div>
                            <div class="grid grid-cols-1 gap-2.5">
                                <button
                                    v-for="role in roles"
                                    :key="role.id"
                                    type="button"
                                    @click="form.role = role.id"
                                    :class="[
                                        'flex flex-col p-3.5 rounded-xl border text-left transition-all',
                                        form.role === role.id
                                            ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-500/20'
                                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-500'
                                    ]"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-bold">{{ role.name }}</span>
                                        <div v-if="form.role === role.id" class="w-4 h-4 rounded-full bg-white flex items-center justify-center">
                                            <svg class="w-3 h-3 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p :class="['text-xs leading-relaxed', form.role === role.id ? 'text-indigo-100' : 'text-gray-500 dark:text-gray-400']">
                                        {{ role.description }}
                                    </p>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Form -->
                    <div class="shrink-0 px-6 py-3.5 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="cancel"
                            class="px-5 py-2.5 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        >Cancelar</button>
                        <button
                            type="button"
                            @click="save"
                            :disabled="!canSave || saving"
                            class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            {{ saving ? 'Guardando...' : showForm ? 'Añadir al espacio' : 'Actualizar rol' }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </ModalView>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import SmartSelect from '@/Components/Common/SmartSelect.vue';

const props = defineProps({
    show: Boolean,
    projectKey: String,
    spaceName: { type: String, default: '' }, // Nombre del espacio activo (para el título dinámico)
    canEdit: Boolean,
    availableUsers: Array, // Usuarios con gestion-proyectos.ver + gestion-proyectos.miembro (filtrado en backend/Index)
});

const emit = defineEmits(['update:show', 'members-changed']);

// Título dinámico: muestra el espacio activo para que el usuario sepa dónde está parado.
const modalTitle = computed(() =>
    props.spaceName
        ? `Miembros y Roles`
        : (props.projectKey ? `Miembros y Roles · ${props.projectKey}` : 'Gestión de Miembros y Roles')
);

const members = ref([]);
const teams = ref([]);
const collapsed = ref({});
const search = ref('');
const selectedId = ref(null);
const showForm = ref(false);
const saving = ref(false);
const form = ref({ user_id: null, role: 'ejecutor' });

const roles = [
    { id: 'administrador', name: 'Administrador', description: 'Gestión total de tareas, equipos y miembros, puede eliminar miembros, y tareas dentro de su espacio.' },
    { id: 'ejecutor', name: 'Ejecutor', description: 'Crea y edita tareas dentro de su espacio.' },
    { id: 'aprobador', name: 'Aprobador', description: 'Auditador. Solo tiene permiso para aprobar estados finales (Finalizado, Reprogramado).' },
    { id: 'implementador', name: 'Implementador', description: 'Ejecutor + Aprobador: crea y edita tareas Y aprueba estados finales. No gestiona el espacio.' },
    { id: 'lector', name: 'Lector', description: 'Solo puede ver las tareas del espacio. Sin permisos de escritura ni aprobación.' },
];

const filteredMembers = computed(() => {
    if (!search.value) return members.value;
    const q = search.value.toLowerCase();
    return members.value.filter(m => 
        m.user?.display_name.toLowerCase().includes(q) || 
        m.user?.email.toLowerCase().includes(q)
    );
});

// Miembros agrupados por equipo del espacio (+ grupo "Sin equipo · manuales").
// Un miembro en varios equipos aparece en cada uno. El buscador filtra dentro de los grupos.
const groupedMembers = computed(() => {
    const q = search.value.trim().toLowerCase();
    const matches = (m) =>
        !q ||
        (m.user?.display_name || '').toLowerCase().includes(q) ||
        (m.user?.email || '').toLowerCase().includes(q);

    const teamUserIds = new Set();
    (teams.value || []).forEach((t) =>
        (t.members || []).forEach((tm) => teamUserIds.add(String(tm.id)))
    );

    const groups = [];
    (teams.value || []).forEach((t) => {
        const ids = new Set((t.members || []).map((tm) => String(tm.id)));
        const mem = members.value.filter((m) => ids.has(String(m.user_id)) && matches(m));
        if (mem.length) groups.push({ key: 'team-' + t.id, label: t.name, members: mem });
    });

    const orphan = members.value.filter((m) => !teamUserIds.has(String(m.user_id)) && matches(m));
    if (orphan.length) groups.push({ key: 'none', label: 'Sin equipo · manuales', members: orphan });

    return groups;
});

function toggleGroup(key) {
    collapsed.value = { ...collapsed.value, [key]: !collapsed.value[key] };
}


const availableUserOptions = computed(() => {
    // Mostrar solo usuarios con gestion-proyectos.miembro (o admin) que no sean ya miembros
    const memberUserIds = new Set(members.value.map(m => String(m.user_id)));
    return props.availableUsers
        .filter(u => !memberUserIds.has(String(u.account_id)))
        .map(u => ({
            value: u.account_id,
            label: `${u.display_name} (${u.email})`,
        }));
});

const canSave = computed(() => {
    if (showForm.value && !form.value.user_id) return false;
    return !!form.value.role;
});

watch(() => props.show, (val) => {
    if (val) { loadMembers(); loadTeams(); }
    else cancel();
});

async function loadMembers() {
    try {
        const resp = await fetch(route('gestion-proyectos.spaces.members.index', props.projectKey));
        if (resp.ok) members.value = await resp.json();
    } catch (err) {
        console.error("Error al cargar miembros:", err);
    }
}

async function loadTeams() {
    if (!props.projectKey) { teams.value = []; return; }
    try {
        const { data } = await window.axios.get(route('gestion-proyectos.teams.index', props.projectKey));
        teams.value = Array.isArray(data) ? data : [];
    } catch {
        teams.value = [];
    }
}

function selectMember(m) {
    showForm.value = false;
    selectedId.value = m.id;
    form.value.role = m.role;
    form.value.user_id = m.user_id;
}

function cancel() {
    showForm.value = false;
    selectedId.value = null;
}

function close() {
    emit('update:show', false);
}

async function save() {
    if (!canSave.value || saving.value) return;
    saving.value = true;
    
    const isEdit = !!selectedId.value;
    const url = isEdit 
        ? route('gestion-proyectos.spaces.members.update', selectedId.value)
        : route('gestion-proyectos.spaces.members.store', props.projectKey);
    
    try {
        // axios envía la cookie XSRF-TOKEN (siempre fresca) y evita el 419 que provocaba
        // el token estancado del <meta name="csrf-token"> al usar fetch.
        if (isEdit) {
            await window.axios.patch(url, form.value);
        } else {
            await window.axios.post(url, form.value);
        }

        window.showToast(isEdit ? 'Rol actualizado' : 'Miembro añadido', 'success');
        await loadMembers();
        cancel();
        emit('members-changed');
    } catch (err) {
        // 422 con { error } = regla de negocio (p.ej. ya hay Propietario); sin response = red.
        const msg = err.response
            ? (err.response.data?.error || 'Error al guardar')
            : 'Error de conexión';
        window.showToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

function getRoleStyle(role) {
    switch (role) {
        case 'propietario': return 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800';
        case 'administrador': return 'bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800';
        case 'aprobador': return 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800';
        case 'ejecutor': return 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800';
        case 'implementador': return 'bg-teal-100 text-teal-700 border-teal-200 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-800';
        default: return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
    }
}
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
</style>
