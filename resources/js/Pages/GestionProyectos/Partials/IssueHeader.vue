<script setup>
import ProjectSwitcher from '@/Components/Modules/GestionProyectos/ProjectSwitcher.vue';
import IssueFiltersDropdown from '@/Components/Modules/GestionProyectos/IssueFiltersDropdown.vue';
import GroupByDropdown from '@/Components/Modules/GestionProyectos/GroupByDropdown.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';

const props = defineProps({
    currentProject: { type: Object, default: null },
    form: { type: Object, required: true },
    projects: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    options: { type: Object, default: () => ({}) },
    localFilter: { type: Object, default: () => ({}) },
    principalItems: { type: Array, default: () => [] },
    uniqueUsers: { type: Array, default: () => [] },
    uniquePriorities: { type: Array, default: () => [] },
    uniqueTypes: { type: Array, default: () => [] },
    filteredCount: { type: Number, default: 0 },
    totalCount: { type: Number, default: 0 },
    activeLocalFilters: { type: Number, default: 0 },
    tableLoading: { type: Boolean, default: false },
    canUserWrite: { type: Boolean, default: false },
    teamsLocal: { type: Array, default: () => [] },
    visibleColumnsCount: { type: Number, default: 0 },
    canCreateProject: { type: Boolean, default: false },
});

defineEmits([
    'apply-filters',
    'clear-filters',
    'open-create-modal',
    'open-teams-modal',
    'open-roles-modal',
    'open-column-panel',
    'open-create-project-modal',
    'open-edit-project-modal',
    'switch-project'
]);
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <!-- Subtítulo con proyecto seleccionado + Selector de proyectos -->
        <ProjectSwitcher
            :current-project="currentProject"
            :current-key="form.project"
            :projects="projects"
            :can-edit="canEdit"
            @edit="$emit('open-edit-project-modal', currentProject)"
            @switch="(key) => $emit('switch-project', key)"
        />

        <div class="flex items-center gap-2 flex-wrap md:ml-auto">
            <!-- Search input -->
            <div class="relative">
                <svg
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"
                    />
                </svg>
                <input
                    v-model="form.search"
                    @keyup.enter="$emit('apply-filters')"
                    type="text"
                    placeholder="Buscar actividad…"
                    class="h-9 pl-8 pr-3 text-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-colors"
                />
            </div>

            <IssueFiltersDropdown
                :form="form"
                :local-filter="localFilter"
                :options="options"
                :principal-items="principalItems"
                :unique-users="uniqueUsers"
                :unique-priorities="uniquePriorities"
                :unique-types="uniqueTypes"
                :filtered-count="filteredCount"
                :total-count="totalCount"
                :active-local-filters="activeLocalFilters"
                :table-loading="tableLoading"
                @apply="$emit('apply-filters')"
                @clear="$emit('clear-filters')"
            />

            <GroupByDropdown
                v-model="form.group_by"
                :options="options.group_by"
                @change="$emit('apply-filters')"
            />

            <!-- ═══ Acción PRIMARIA: Crear Actividad ═══ -->
            <button
                v-if="canUserWrite"
                @click="$emit('open-create-modal')"
                title="Nueva actividad (C)"
                class="inline-flex items-center gap-1.5 h-9 px-4 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm hover:shadow-md transition-all duration-150"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nueva actividad
            </button>

            <button
                v-if="canUserWrite"
                @click="$emit('open-teams-modal')"
                title="Gestionar equipos del espacio"
                class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 transition-colors duration-150"
            >
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                Equipos
                <span
                    v-if="teamsLocal.length"
                    class="text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 px-1.5 py-0.5 rounded-full ml-0.5"
                >{{ teamsLocal.length }}</span>
            </button>

            <!-- ═══ Acción SECUNDARIA: Roles (Gestión de miembros) ═══ -->
            <button
                v-if="canUserWrite"
                @click="$emit('open-roles-modal')"
                title="Gestionar roles y permisos del espacio"
                class="inline-flex items-center gap-1.5 h-9 px-3 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60 hover:border-gray-300 dark:hover:border-gray-600 transition-colors duration-150"
            >
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
                Roles
            </button>

            <!-- ═══ Menú "Más acciones" (kebab) — acciones poco frecuentes ═══ -->
            <Dropdown align="right" minWidth="220px">
                <template #trigger>
                    <button
                        title="Más acciones"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/60 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600 transition-colors duration-150"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                        </svg>
                    </button>
                </template>
                <template #content="{ close }">
                    <div class="py-1">
                        <!-- Configurar columnas -->
                        <button
                            v-if="canUserWrite"
                            type="button"
                            @click="$emit('open-column-panel'); close();"
                            class="w-full flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-left"
                        >
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <span class="flex-1">Configurar columnas</span>
                            <span class="text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded-full">
                                {{ visibleColumnsCount }}
                            </span>
                        </button>

                        <!-- Crear espacio nuevo (acción rara) -->
                        <button
                            v-if="canCreateProject"
                            type="button"
                            @click="$emit('open-create-project-modal'); close();"
                            class="w-full flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-left"
                        >
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                            </svg>
                            <span class="flex-1">Crear nuevo espacio</span>
                        </button>
                    </div>
                </template>
            </Dropdown>
        </div>
    </div>
</template>
