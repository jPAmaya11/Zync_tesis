<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        title="Gestión de Equipos"
        size="4xl"
        :hide-footer="true"
        min-height="h-[70vh]"
        @close="close"
    >
        <template #subtitle>
            <template v-if="projectKey">
                Espacio
                <span class="font-mono font-semibold text-white/90">{{ projectKey }}</span>
                <span class="opacity-70">·</span>
            </template>
            <template v-else>Equipos globales <span class="opacity-70">·</span> </template>
            {{ localTeams.length }} equipo{{ localTeams.length !== 1 ? 's' : '' }}
        </template>

        <!-- Layout 2 columnas — altura fija, scroll interno.
             -mx-8/-mb-6 hacen full-bleed lateral e inferior (cancelan el padding del modal);
             arriba NO se cancela el padding → queda un respiro y no se pega al header. -->
        <div class="-mx-8 -mb-6 flex flex-col md:flex-row h-[70vh] overflow-hidden border-t border-gray-100 dark:border-gray-800">
            <!-- ═══ COLUMNA IZQ: LISTA DE EQUIPOS ═══ -->
            <aside class="w-full md:w-72 shrink-0 border-r border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30 flex flex-col overflow-hidden">
                <!-- Header con buscador + crear -->
                <div class="shrink-0 px-4 py-3 border-b border-gray-100 dark:border-gray-800 space-y-2">
                    <button
                        v-if="canEdit"
                        type="button"
                        @click="startCreate"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-all"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo equipo
                    </button>

                    <div v-if="localTeams.length > 3" class="relative">
                        <svg
                            class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                        </svg>
                        <input
                            v-model="teamSearch"
                            type="text"
                            placeholder="Buscar equipo..."
                            class="w-full h-8 pl-8 pr-2 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                        />
                    </div>
                </div>

                <!-- Lista scrolleable (la única que crece) -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1 min-h-0">
                    <div
                        v-if="!filteredTeams.length"
                        class="flex flex-col items-center justify-center py-10 text-center text-gray-400 dark:text-gray-500 px-4"
                    >
                        <svg class="w-8 h-8 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <p class="text-xs font-medium">
                            {{ teamSearch ? 'Sin coincidencias' : 'Sin equipos' }}
                        </p>
                    </div>

                    <button
                        v-for="team in filteredTeams"
                        :key="team.id"
                        type="button"
                        @click="selectTeam(team)"
                        :class="[
                            'w-full text-left px-3 py-2.5 rounded-lg border transition-all group',
                            editingId === team.id
                                ? 'bg-indigo-500/10 border-indigo-300 dark:border-indigo-700 ring-1 ring-indigo-500/30'
                                : 'border-transparent hover:bg-white dark:hover:bg-gray-800 hover:border-gray-200 dark:hover:border-gray-700',
                            team.is_active === false ? 'opacity-60' : '',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        :class="[
                                            'text-sm font-semibold truncate',
                                            editingId === team.id
                                                ? 'text-indigo-700 dark:text-indigo-300'
                                                : 'text-gray-800 dark:text-gray-200',
                                        ]"
                                    >{{ team.name }}</p>
                                    <!-- Contador de miembros del equipo -->
                                    <span
                                        class="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                        :title="`${team.members?.length || 0} miembro(s)`"
                                    >
                                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                        {{ team.members?.length || 0 }}
                                    </span>
                                    <!-- La lista muestra todos los equipos globales; este badge marca
                                         cuáles están asignados al espacio activo (evita confusión con el contador). -->
                                    <span
                                        v-if="projectKey && isTeamInProject(team.id)"
                                        class="shrink-0 px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                        title="Asignado a este espacio"
                                    >EN ESPACIO</span>
                                    <!-- Estado del equipo: archivado/inactivo -->
                                    <span
                                        v-if="team.is_active === false"
                                        class="shrink-0 px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                                        title="Equipo desactivado (no se ofrece para nuevas asignaciones)"
                                    >INACTIVO</span>
                                </div>
                                
                                <!-- Mini avatares apilados con fallback -->
                                <div v-if="team.members?.length" class="flex items-center -space-x-1.5 mt-1.5">
                                    <UserAvatar
                                        v-for="(m, idx) in team.members.slice(0, 4)"
                                        :key="m.id"
                                        :src="m.avatar_url"
                                        :name="m.display_name"
                                        size="sm"
                                        ring
                                        :style="`z-index: ${10 - idx}`"
                                    />
                                    <span
                                        v-if="team.members.length > 4"
                                        class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[8px] font-bold flex items-center justify-center border-2 border-white dark:border-gray-800"
                                    >+{{ team.members.length - 4 }}</span>
                                </div>
                                <p v-else class="text-[10px] text-gray-400 mt-1 italic">Sin miembros</p>
                            </div>
                            <span
                                v-if="editingId === team.id"
                                class="shrink-0 w-1.5 h-1.5 rounded-full bg-indigo-500 mt-2"
                            ></span>
                        </div>
                    </button>
                </div>
            </aside>

            <!-- ═══ COLUMNA DER: FORMULARIO / DETALLE / EMPTY ═══ -->
            <section class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden">
                <!-- Empty state -->
                <div
                    v-if="!showAddForm && !editingId"
                    class="flex-1 flex flex-col items-center justify-center text-center p-8"
                >
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-100 to-cyan-100 dark:from-indigo-900/30 dark:to-cyan-900/20 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-1">
                        {{ localTeams.length ? 'Selecciona un equipo' : 'Crea tu primer equipo' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-xs">
                        {{
                            localTeams.length
                                ? 'Elige un equipo de la izquierda para ver o editar sus miembros, o crea uno nuevo.'
                                : 'Organiza a las personas de tu espacio en equipos para asignar tareas más fácilmente.'
                        }}
                    </p>
                    <button
                        v-if="canEdit"
                        type="button"
                        @click="startCreate"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Crear equipo
                    </button>
                </div>

                <!-- Formulario -->
                <template v-else>
                    <!-- Header sticky del formulario: título + tabs Datos / Miembros -->
                    <div class="shrink-0 px-6 pt-4 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900">
                        
                        <!-- Tabs de secciones (subrayado) -->
                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="formTab = 'datos'"
                                :class="['px-3 py-2 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap', formTab === 'datos' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300']"
                            >
                                Datos
                            </button>
                            <button
                                type="button"
                                @click="formTab = 'miembros'"
                                :class="['px-3 py-2 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap inline-flex items-center gap-1.5', formTab === 'miembros' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300']"
                            >
                                Miembros
                                <span
                                    v-if="form.member_ids.length"
                                    class="flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] font-bold rounded-full bg-indigo-500 text-white"
                                >{{ form.member_ids.length }}</span>
                            </button>
                            <!-- Destructivo: estado del equipo + eliminar (solo edición + permiso) -->
                            <button
                                v-if="editingId && canEdit"
                                type="button"
                                @click="formTab = 'destructivo'"
                                :class="['px-3 py-2 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap inline-flex items-center gap-1.5', formTab === 'destructivo' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300']"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                                Destructivo
                            </button>
                        </div>
                    </div>

                    <!-- Body scrolleable -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar px-6 py-4 min-h-0">
                        <!-- ════ TAB: DATOS (nombre, descripción, asignar al espacio) ════ -->
                        <div v-show="formTab === 'datos'" class="space-y-5">
                        <!-- Nombre + Descripción -->
                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                    Nombre <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    maxlength="100"
                                    placeholder="Ej: Backend, QA…"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                                />
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                    Descripción
                                </label>
                                <input
                                    v-model="form.description"
                                    type="text"
                                    maxlength="200"
                                    placeholder="Breve descripción del propósito del equipo…"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                                />
                            </div>
                        </div>

                        <!-- Asignación al espacio (solo con espacio activo) -->
                        <div v-if="projectKey" class="rounded-xl border border-indigo-100 dark:border-indigo-900/30 bg-indigo-50/30 dark:bg-indigo-900/10 p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-indigo-900 dark:text-indigo-200">Asignar a este Espacio</p>
                                <p class="text-[11px] text-indigo-700/70 dark:text-indigo-300/60">Permite que este equipo aparezca en las actividades de este proyecto.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
  <input
    type="checkbox"
    v-model="form.is_in_project"
    class="sr-only peer"
  >
  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
</label>
                        </div>

                        </div><!-- /tab datos -->

                        <!-- ════ TAB: MIEMBROS (selector de personas) ════ -->
                        <div v-show="formTab === 'miembros'">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                        Miembros del equipo
                                    </p>
                               
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-full">
                                        {{ form.member_ids.length }} / {{ availableUsers.length }}
                                    </span>
                                    
                                </div>
                            </div>

                            <!-- Chips de seleccionados -->
                            <div
                                v-if="form.member_ids.length > 0"
                                class="flex flex-wrap gap-1.5 mb-3 p-2 rounded-lg bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-900/30"
                            >
                                <span
                                    v-for="u in selectedMembers"
                                    :key="u.account_id"
                                    class="inline-flex items-center gap-1.5 pl-1 pr-2 py-0.5 rounded-full bg-white dark:bg-gray-800 text-[11px] font-medium text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 shadow-sm"
                                >
                                    <UserAvatar :src="u.avatar_url" :name="u.display_name" size="xs" />
                                    {{ u.display_name }}
                                    <button
                                        type="button"
                                        @click.stop="toggleMember(u.account_id)"
                                        class="text-gray-400 hover:text-red-500 -mr-0.5"
                                    >
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            </div>

                            <!-- Buscador -->
                            <div class="relative mb-2">
                                <svg
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                </svg>
                                <input
                                    v-model="memberSearch"
                                    type="text"
                                    placeholder="Buscar por nombre o email..."
                                    class="w-full h-10 pl-10 pr-9 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                                />
                                <button
                                    v-if="memberSearch"
                                    type="button"
                                    @click="memberSearch = ''"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Lista de usuarios filtrada con altura máxima fija -->
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 max-h-64 overflow-y-auto custom-scrollbar divide-y divide-gray-100 dark:divide-gray-800">
                                <div
                                    v-if="!filteredUsers.length"
                                    class="flex flex-col items-center justify-center py-8 text-center text-gray-400"
                                >
                                    <p class="text-xs font-medium">
                                        {{ memberSearch ? 'Sin coincidencias para tu búsqueda' : 'Sin usuarios disponibles' }}
                                    </p>
                                </div>

                                <button
                                    v-for="u in filteredUsers"
                                    :key="u.account_id"
                                    type="button"
                                    @click="toggleMember(u.account_id)"
                                    :class="[
                                        'w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                        isSelected(u.account_id)
                                            ? 'bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/30'
                                            : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40',
                                    ]"
                                >
                                    <UserAvatar :src="u.avatar_url" :name="u.display_name" size="lg" />

                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">
                                            {{ u.display_name }}
                                        </p>
                                        <p v-if="u.email" class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ u.email }}
                                        </p>
                                    </div>

                                    <span
                                        :class="[
                                            'shrink-0 w-5 h-5 rounded-md flex items-center justify-center border-2 transition-all',
                                            isSelected(u.account_id)
                                                ? 'bg-indigo-600 border-indigo-600'
                                                : 'border-gray-300 dark:border-gray-600 bg-transparent',
                                        ]"
                                    >
                                        <svg
                                            v-if="isSelected(u.account_id)"
                                            class="w-3 h-3 text-white"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- ════ TAB: DESTRUCTIVO (estado del equipo + eliminar) ════ -->
                        <div v-show="formTab === 'destructivo'" class="space-y-5">
                            <!-- Activar / Desactivar equipo (instantáneo) -->
                            <div
                                :class="[
                                    'rounded-xl border p-4',
                                    form.is_active
                                        ? 'border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40'
                                        : 'border-amber-200 dark:border-amber-800/50 bg-amber-50/60 dark:bg-amber-900/10',
                                ]"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-gray-800 dark:text-gray-200">
                                            {{ form.is_active ? 'Equipo activo' : 'Equipo desactivado' }}
                                        </p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 max-w-md">
                                            Al desactivarlo se cierra la puerta: quienes accedían al espacio
                                            <strong>por este equipo</strong> dejan de entrar (su rol se conserva).
                                            No afecta a miembros manuales ni al propietario, ni a las actividades ya
                                            asignadas. Es <strong>reversible</strong>: al reactivarlo todo vuelve como antes.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="form.is_active"
                                        :disabled="togglingActive"
                                        @click="toggleTeamActive"
                                        :class="[
                                            'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50 mt-0.5',
                                            form.is_active ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600',
                                        ]"
                                        :title="form.is_active ? 'Desactivar equipo' : 'Activar equipo'"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
                                                form.is_active ? 'translate-x-5' : 'translate-x-0.5',
                                            ]"
                                        ></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Eliminar equipo (permanente) -->
                            <div class="rounded-xl border border-rose-200 dark:border-rose-800/50 bg-rose-50/60 dark:bg-rose-900/10 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-rose-700 dark:text-rose-300">Eliminar equipo</p>
                                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-0.5 max-w-md">
                                            Acción permanente. Los miembros <strong>no</strong> se borran (solo dejan de
                                            pertenecer al equipo) y las actividades que lo tenían asignado quedan sin equipo.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="confirmAndDelete()"
                                        title="Eliminar equipo"
                                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                        Eliminar equipo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer sticky: Cancelar / Guardar -->
                    <div class="shrink-0 px-6 py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30 flex items-center justify-end gap-2">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="cancelForm"
                                class="px-4 py-2 text-xs font-medium text-gray-600 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-white dark:hover:bg-gray-700 transition-colors"
                            >Cancelar</button>
                            <button
                                type="button"
                                :disabled="!form.name.trim() || saving"
                                @click="submit"
                                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
                            >
                                <svg v-if="saving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                </svg>
                                {{ submitButtonText }}
                            </button>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </ModalView>
</template>

<script setup>
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    teams: { type: Array, default: () => [] },
    projectKey: { type: String, default: '' },
    canEdit: { type: Boolean, default: false },
    availableUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:show', 'teams-changed', 'error']);

const localTeams = ref([...props.teams]);
const showAddForm = ref(false);
const saving = ref(false);
const editingId = ref(null);
const formTab = ref('datos'); // tab activo del panel derecho: 'datos' | 'miembros' | 'destructivo'
const form = ref({ name: '', description: '', member_ids: [], is_in_project: true, is_active: true });
const allGlobalTeams = ref([]);
const togglingActive = ref(false);
const teamSearch = ref('');
const memberSearch = ref('');

watch(
    () => props.teams,
    (fresh) => { localTeams.value = [...fresh]; },
    { deep: true }
);

watch(
    () => props.show,
    (val) => {
        if (val) {
            loadAllTeams();
        } else {
            showAddForm.value = false;
            editingId.value = null;
            formTab.value = 'datos';
            form.value = { name: '', description: '', member_ids: [], is_in_project: true, is_active: true };
            teamSearch.value = '';
            memberSearch.value = '';
        }
    }
);

async function loadAllTeams() {
    try {
        const resp = await fetch(route('gestion-proyectos.teams.all'));
        if (resp.ok) {
            allGlobalTeams.value = await resp.json();
            // Sin espacio activo no hay lista "de proyecto": la global ES la lista visible.
            if (!props.projectKey) {
                localTeams.value = allGlobalTeams.value;
            }
        }
    } catch (err) {
        console.error("Error al cargar equipos globales:", err);
    }
}

function isTeamInProject(teamId) {
    return props.teams.some(t => t.id === teamId);
}

const filteredTeams = computed(() => {
    if (!teamSearch.value) return allGlobalTeams.value;
    const q = teamSearch.value.toLowerCase();
    return allGlobalTeams.value.filter((t) =>
        t.name.toLowerCase().includes(q) ||
        (t.description || '').toLowerCase().includes(q)
    );
});

const filteredUsers = computed(() => {
    if (!memberSearch.value) return props.availableUsers;
    const q = memberSearch.value.toLowerCase();
    return props.availableUsers.filter((u) =>
        u.display_name.toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q)
    );
});

const selectedMembers = computed(() =>
    props.availableUsers.filter((u) => form.value.member_ids.includes(String(u.account_id)))
);

const submitButtonText = computed(() => {
    if (saving.value) return 'Guardando...';
    if (!editingId.value) return 'Crear equipo';
    
    // Si el equipo seleccionado NO está asignado a este proyecto aún, el botón debe decir "Asignar equipo"
    if (!isTeamInProject(editingId.value)) {
        return 'Asignar equipo';
    }
    
    return 'Guardar cambios';
});

function close() { emit('update:show', false); }

function startCreate() {
    showAddForm.value = true;
    editingId.value = null;
    formTab.value = 'datos';
    form.value = { name: '', description: '', member_ids: [], is_in_project: true, is_active: true };
    memberSearch.value = '';
}

function selectTeam(team) {
    editingId.value = team.id;
    showAddForm.value = true;
    formTab.value = 'datos';
    form.value = {
        name: team.name,
        description: team.description || '',
        // Normalizar a String para comparación consistente con account_id de availableUsers
        member_ids: (team.members || []).map((m) => String(m.id)),
        is_in_project: isTeamInProject(team.id),
        is_active: team.is_active !== false,
    };
    memberSearch.value = '';
}

function cancelForm() {
    showAddForm.value = false;
    editingId.value = null;
    form.value = { name: '', description: '', member_ids: [], is_in_project: true, is_active: true };
}

// Activar / desactivar el equipo (instantáneo). NO toca miembros, accesos ni
// asignaciones existentes: solo cambia si el equipo se ofrece para nuevas asignaciones.
async function toggleTeamActive() {
    if (!editingId.value || togglingActive.value) return;
    const next = !form.value.is_active;
    togglingActive.value = true;
    try {
        await window.axios.patch(
            route('gestion-proyectos.teams.toggle-active', { id: editingId.value }),
            { is_active: next }
        );
        form.value.is_active = next;
        // Reflejar el estado en la lista (badge) sin recargar.
        const t = localTeams.value.find((x) => x.id === editingId.value);
        if (t) t.is_active = next;
        const ag = allGlobalTeams.value.find((x) => x.id === editingId.value);
        if (ag) ag.is_active = next;
        emit('teams-changed', localTeams.value);
        window.showToast(
            next
                ? 'Equipo activado. Se restauró el acceso de sus miembros.'
                : 'Equipo desactivado. Quienes accedían por este equipo quedaron suspendidos (reversible al reactivar).',
            'success',
            { timer: 3500 }
        );
    } catch (err) {
        const msg = err.response?.data?.error || 'No se pudo cambiar el estado del equipo.';
        emit('error', msg);
    } finally {
        togglingActive.value = false;
    }
}

function isSelected(accountId) {
    return form.value.member_ids.includes(String(accountId));
}

function toggleMember(accountId) {
    const strId = String(accountId);
    const idx = form.value.member_ids.indexOf(strId);
    if (idx >= 0) form.value.member_ids.splice(idx, 1);
    else form.value.member_ids.push(strId);
}

async function submit() {
    if (!form.value.name.trim() || saving.value) return;
    saving.value = true;
    const isEdit = editingId.value !== null;
    // Sin espacio activo, los equipos se crean globalmente (no se adjuntan a ningún espacio).
    const url = isEdit
        ? route('gestion-proyectos.teams.update', { id: editingId.value })
        : (props.projectKey
            ? route('gestion-proyectos.teams.store', { projectKey: props.projectKey })
            : route('gestion-proyectos.teams.global.store'));
    const method = isEdit ? 'patch' : 'post';

    // axios lee la cookie XSRF-TOKEN (siempre fresca) → evita el 419 del meta tag obsoleto.
    try {
        const { data: saved } = await window.axios({
            url,
            method,
            data: {
                ...form.value,
                // El backend espera integers en member_ids (exists:users,id)
                member_ids: form.value.member_ids.map((id) => parseInt(id)).filter(Boolean),
            },
        });

        // La sincronización al espacio sólo aplica si hay un espacio activo.
        // En edición, sincronizar asignación al proyecto sólo si el toggle cambió.
        // En creación no hace falta: el controller ya adjunta el equipo al proyecto.
        if (props.projectKey && isEdit && form.value.is_in_project !== isTeamInProject(saved.id)) {
            const currentTeamIds = props.teams.map(t => t.id);
            const newTeamIds = form.value.is_in_project
                ? [...currentTeamIds, saved.id]
                : currentTeamIds.filter(id => id !== saved.id);

            await window.axios.post(
                route('gestion-proyectos.projects.teams.sync', { projectKey: props.projectKey }),
                { team_ids: newTeamIds }
            );
        }

        await loadAllTeams(); // Recargar lista global

        // Recargar equipos del proyecto para actualizar el padre (Index.vue).
        // Sin espacio activo no hay lista de proyecto que recargar; usamos la global.
        if (props.projectKey) {
            const { data: projectTeams } = await window.axios.get(
                route('gestion-proyectos.teams.index', { projectKey: props.projectKey })
            );
            localTeams.value = projectTeams;
            emit('teams-changed', projectTeams);
        } else {
            localTeams.value = allGlobalTeams.value;
            emit('teams-changed', allGlobalTeams.value);
        }

        cancelForm();
        window.showToast(
            isEdit ? 'Equipo actualizado.' : 'Equipo creado.',
            'success',
            { timer: 3000 }
        );
    } catch (err) {
        console.error('[TeamsManagementModal] submit error:', err);
        const msg = err.response?.data?.error
            || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' · ') : null)
            || 'Error de conexión al guardar el equipo.';
        emit('error', msg);
    } finally {
        saving.value = false;
    }
}

async function confirmAndDelete() {
    if (!editingId.value) return;
    const team = localTeams.value.find((t) => t.id === editingId.value);
    if (!team) return;

    const confirmed = window.showConfirm
        ? await window
              .showConfirm(
                  '¿Eliminar equipo?',
                  `El equipo "${team.name}" se eliminará permanentemente. Los miembros no se borran, solo dejan de pertenecer al equipo.`,
                  { confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }
              )
              .then((r) => r.isConfirmed)
        : window.confirm(`¿Eliminar equipo "${team.name}"?`);

    if (!confirmed) return;

    try {
        await window.axios.delete(route('gestion-proyectos.teams.destroy', { id: team.id }));
        localTeams.value = localTeams.value.filter((t) => t.id !== team.id);
        cancelForm();
        emit('teams-changed', localTeams.value);
        window.showToast('Equipo eliminado.', 'success', { timer: 3000 });
    } catch (err) {
        const msg = err.response?.data?.error || 'Error al eliminar el equipo.';
        emit('error', msg);
    }
}
</script>
