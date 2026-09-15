<template>
    <Dropdown align="right" minWidth="640px" maxWidth="640px">
        <template #trigger>
            <button
                class="inline-flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-lg shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px] text-sm font-medium"
            >
                <svg
                    class="w-5 h-5 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 12h12m-9 8h6" />
                </svg>
                <span>Filtrar</span>
                <span
                    v-if="totalActiveFilters > 0"
                    class="ml-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-white text-indigo-700"
                >
                    {{ totalActiveFilters }}
                </span>
            </button>
        </template>

        <template #content="{ close }">
            <div class="flex flex-col h-[480px] overflow-hidden" @click.stop>
                <!-- ── Cabecera única: tabs de secciones (subrayado) + cerrar ── -->
                <div class="shrink-0 flex items-end px-4 pt-2 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <button
                        type="button"
                        @click.stop="activeTab = 'atributos'"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-1.5', activeTab === 'atributos' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >
                        Atributos
                        <span
                            v-if="atributosActiveCount"
                            class="flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] font-bold rounded-full bg-indigo-500 text-white"
                        >{{ atributosActiveCount }}</span>
                    </button>
                    <button
                        type="button"
                        @click.stop="activeTab = 'personas'"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-1.5', activeTab === 'personas' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >
                        Personas
                        <span
                            v-if="personasActiveCount"
                            class="flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] font-bold rounded-full bg-indigo-500 text-white"
                        >{{ personasActiveCount }}</span>
                    </button>
                    <button
                        type="button"
                        @click.stop="activeTab = 'fecha'"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-1.5', activeTab === 'fecha' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >
                        Fecha
                        <span
                            v-if="fechaActiveCount"
                            class="flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] font-bold rounded-full bg-indigo-500 text-white"
                        >{{ fechaActiveCount }}</span>
                    </button>
                    <button
                        type="button"
                        @click.stop="activeTab = 'vista'"
                        :class="['px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-1.5', activeTab === 'vista' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                    >
                        Vista
                        <span
                            v-if="vistaActiveCount"
                            class="flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[9px] font-bold rounded-full bg-indigo-500 text-white"
                        >{{ vistaActiveCount }}</span>
                    </button>
                    <!-- Cerrar (en la cabecera de tabs) -->
                    <button
                        @click="close"
                        class="ml-auto mb-1.5 p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-zinc-200 hover:bg-gray-100 dark:hover:bg-zinc-800 transition"
                        title="Cerrar"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- ── Contenido del tab activo (scroll) ── -->
                <div class="flex-1 min-h-0 p-5 overflow-y-auto custom-scrollbar bg-white dark:bg-gray-900">
                    <!-- ════ TAB: ATRIBUTOS ════ -->
                    <div v-show="activeTab === 'atributos'" class="space-y-6">
                        <!-- Estado -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Estado
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="s in options.statuses"
                                    :key="s"
                                    type="button"
                                    @click.stop="toggleArrayValue(form.statuses, s)"
                                    :class="[
                                        'px-3 py-1.5 text-[11px] font-semibold rounded-md border transition-colors duration-150',
                                        form.statuses.includes(s)
                                            ? 'bg-indigo-600 text-white border-indigo-500 shadow-md'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700 hover:text-gray-900 dark:hover:text-zinc-200',
                                    ]"
                                >
                                    {{ s }}
                                </button>
                            </div>
                        </div>

                        <!-- Prioridad -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Prioridad
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="p in uniquePriorities"
                                    :key="p.name"
                                    type="button"
                                    @click.stop="toggleArrayValue(form.priorities, p.name)"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-md border transition-colors duration-150',
                                        form.priorities.includes(p.name)
                                            ? 'bg-indigo-600 text-white border-indigo-500 shadow-md'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700 hover:text-gray-900 dark:hover:text-zinc-200',
                                    ]"
                                >
                                    <img v-if="p.icon_url" :src="p.icon_url" class="w-3.5 h-3.5 shrink-0" :alt="p.name" />
                                    {{ p.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Tipo de Actividad -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Tipo de Actividad
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="t in uniqueTypes"
                                    :key="t.name"
                                    type="button"
                                    @click.stop="localFilter.type = localFilter.type === t.name ? '' : t.name"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-md border transition-colors duration-150',
                                        localFilter.type === t.name
                                            ? 'bg-indigo-600 text-white border-indigo-500 shadow-md'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700 hover:text-gray-900 dark:hover:text-zinc-200',
                                    ]"
                                >
                                    <img v-if="t.icon_url" :src="t.icon_url" class="w-3.5 h-3.5 shrink-0 rounded-sm" :alt="issueTypeLabel(t.name)" />
                                    {{ issueTypeLabel(t.name) }}
                                </button>
                            </div>
                        </div>

                        <!-- Impacto -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Impacto
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="imp in (options.impacto_values || [])"
                                    :key="imp"
                                    type="button"
                                    @click.stop="localFilter.impacto = localFilter.impacto === imp ? '' : imp"
                                    :class="[
                                        'px-3 py-1.5 text-[11px] font-semibold rounded-md border transition-colors duration-150',
                                        localFilter.impacto === imp
                                            ? 'bg-indigo-600 text-white border-indigo-500 shadow-md'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700 hover:text-gray-900 dark:hover:text-zinc-200',
                                    ]"
                                >
                                    {{ imp }}
                                </button>
                            </div>
                        </div>

                        <!-- Etiquetas -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Etiquetas
                            </p>
                            <div class="flex flex-wrap gap-2 max-h-72 overflow-y-auto p-2 overflow-x-hidden custom-scrollbar">
                                <button
                                    v-for="label in options.labels"
                                    :key="label"
                                    type="button"
                                    @click.stop="toggleArrayValue(form.labels, label)"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-bold uppercase tracking-wider rounded-md border transition-all duration-200',
                                        form.labels.includes(label)
                                            ? 'relative z-20 ring-2 ring-indigo-500 ring-offset-2 dark:ring-offset-zinc-900 shadow-lg scale-105 ' + getLabelColor(label)
                                            : 'relative z-10 opacity-90 hover:opacity-100 hover:z-20 ' + getLabelColor(label),
                                    ]"
                                >
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                    {{ label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ════ TAB: PERSONAS ════ -->
                    <div v-show="activeTab === 'personas'" class="space-y-6">
                        <!-- Persona Asignada -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Persona Asignada
                            </p>
                            <div class="grid grid-cols-1 gap-1.5 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar">
                                <button
                                    v-for="u in uniqueUsers"
                                    :key="u.account_id"
                                    type="button"
                                    @click.stop="localFilter.assignee = localFilter.assignee === u.display_name ? '' : u.display_name"
                                    :class="[
                                        'flex items-center gap-3 px-3 py-2 rounded-md border transition-colors duration-150 text-left',
                                        localFilter.assignee === u.display_name
                                            ? 'bg-indigo-600/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/50'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700/50',
                                    ]"
                                >
                                    <img v-if="u.avatar_url" :src="u.avatar_url" class="w-6 h-6 rounded-full border border-gray-200 dark:border-zinc-600" />
                                    <span
                                        v-else
                                        class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center"
                                        >{{ initials(u.display_name) }}</span
                                    >
                                    <span class="text-[12px] font-medium">{{ u.display_name }}</span>
                                    <svg
                                        v-if="localFilter.assignee === u.display_name"
                                        class="ml-auto w-4 h-4 text-indigo-500 dark:text-indigo-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Solicitado Por (campo reporter) -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Solicitado Por
                            </p>
                            <div class="grid grid-cols-1 gap-1.5 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar">
                                <button
                                    v-for="u in uniqueReporters"
                                    :key="u.account_id"
                                    type="button"
                                    @click.stop="localFilter.reporter = localFilter.reporter === u.display_name ? '' : u.display_name"
                                    :class="[
                                        'flex items-center gap-3 px-3 py-2 rounded-md border transition-colors duration-150 text-left',
                                        localFilter.reporter === u.display_name
                                            ? 'bg-indigo-600/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/50'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700/50',
                                    ]"
                                >
                                    <img v-if="u.avatar_url" :src="u.avatar_url" class="w-6 h-6 rounded-full border border-gray-200 dark:border-zinc-600" />
                                    <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center">{{ initials(u.display_name) }}</span>
                                    <span class="text-[12px] font-medium">{{ u.display_name }}</span>
                                    <svg v-if="localFilter.reporter === u.display_name" class="ml-auto w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Creador -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Creador
                            </p>
                            <div class="grid grid-cols-1 gap-1.5 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar">
                                <button
                                    v-for="u in uniqueCreators"
                                    :key="u.account_id"
                                    type="button"
                                    @click.stop="localFilter.creator = localFilter.creator === u.display_name ? '' : u.display_name"
                                    :class="[
                                        'flex items-center gap-3 px-3 py-2 rounded-md border transition-colors duration-150 text-left',
                                        localFilter.creator === u.display_name
                                            ? 'bg-indigo-600/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/50'
                                            : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700/50',
                                    ]"
                                >
                                    <img v-if="u.avatar_url" :src="u.avatar_url" class="w-6 h-6 rounded-full border border-gray-200 dark:border-zinc-600" />
                                    <span v-else class="w-6 h-6 rounded-full bg-indigo-500 text-white text-[10px] font-bold flex items-center justify-center">{{ initials(u.display_name) }}</span>
                                    <span class="text-[12px] font-medium">{{ u.display_name }}</span>
                                    <svg v-if="localFilter.creator === u.display_name" class="ml-auto w-4 h-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ════ TAB: FECHA ════ -->
                    <div v-show="activeTab === 'fecha'" class="space-y-6">
                        <!-- Fecha (rango: creación → límite) -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Fecha
                            </p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 dark:text-zinc-400 mb-1">Desde <span class="font-normal text-gray-400">(fecha de creación)</span></label>
                                    <input
                                        type="date"
                                        v-model="localFilter.dateFrom"
                                        :max="localFilter.dateTo || undefined"
                                        class="w-full h-9 px-3 text-sm rounded-md border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 dark:text-zinc-400 mb-1">Hasta <span class="font-normal text-gray-400">(fecha límite)</span></label>
                                    <input
                                        type="date"
                                        v-model="localFilter.dateTo"
                                        :min="localFilter.dateFrom || undefined"
                                        class="w-full h-9 px-3 text-sm rounded-md border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500"
                                    />
                                </div>
                                <button
                                    v-if="localFilter.dateFrom || localFilter.dateTo"
                                    type="button"
                                    @click.stop="localFilter.dateFrom = ''; localFilter.dateTo = ''"
                                    class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    Limpiar fechas
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ════ TAB: VISTA ════ -->
                    <div v-show="activeTab === 'vista'" class="space-y-6">
                        <!-- Agrupar por (selección única, vive en form.group_by) -->
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-zinc-500 mb-3 flex items-center gap-2">
                                <span class="w-1 h-3 bg-indigo-500 rounded-full"></span>
                                Agrupar por
                            </p>
                            <div class="space-y-0.5">
                                <!-- Sin agrupar -->
                                <button
                                    type="button"
                                    @click.stop="form.group_by = ''"
                                    :class="[
                                        'w-full flex items-center justify-between px-3 py-2 rounded-md text-[13px] transition-colors duration-150',
                                        !form.group_by
                                            ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-semibold'
                                            : 'text-gray-600 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800',
                                    ]"
                                >
                                    Sin agrupar
                                    <svg v-if="!form.group_by" class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <!-- Campos disponibles -->
                                <button
                                    v-for="opt in (options.group_by || [])"
                                    :key="opt.value"
                                    type="button"
                                    @click.stop="form.group_by = opt.value"
                                    :class="[
                                        'w-full flex items-center justify-between px-3 py-2 rounded-md text-[13px] transition-colors duration-150',
                                        form.group_by === opt.value
                                            ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-semibold'
                                            : 'text-gray-600 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800',
                                    ]"
                                >
                                    {{ opt.label }}
                                    <svg v-if="form.group_by === opt.value" class="w-5 h-5 text-indigo-600 dark:text-indigo-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-400 dark:text-zinc-500">
                                Agrupa las filas de la tabla por el campo seleccionado.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer fijo: contador + acciones -->
                <div class="shrink-0 flex items-center justify-between gap-3 px-5 py-3 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/50 dark:bg-white/[0.02]">
                    <span class="text-xs text-gray-500 dark:text-zinc-500">
                        <strong class="text-gray-900 dark:text-zinc-200">{{ filteredCount }}</strong>
                        mostrados
                        <template v-if="totalCount">
                            · <strong class="text-gray-900 dark:text-zinc-200">{{ totalCount }}</strong>
                            en total
                        </template>
                    </span>
                    <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click.stop="$emit('clear')"
                        class="px-4 py-2 text-xs font-medium rounded-sm border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors duration-150"
                    >
                        Limpiar
                    </button>
                    <button
                        type="button"
                        @click.stop="onApply(close)"
                        :disabled="tableLoading"
                        class="px-5 py-2 text-xs font-semibold rounded-sm bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors duration-150 shadow-md"
                    >
                        <span v-if="tableLoading" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Aplicando...
                        </span>
                        <span v-else>Aplicar Filtros</span>
                    </button>
                    </div>
                </div>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import { getLabelColor } from '@/Composables/GestionProyectos/useLabelColors';
import { issueTypeLabel } from '@/Composables/GestionProyectos/useIssueTypeLabel';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Form reactivo (Inertia useForm) — mutado directamente por sus checkboxes. */
    form: { type: Object, required: true },
    /** Filtros locales (assignee, type) — mutados directamente. */
    localFilter: { type: Object, required: true },
    /** Opciones { statuses, priorities, labels, group_by }. */
    options: { type: Object, default: () => ({}) },
    /** Items de categorías (compat; ya no se usa con el layout de tabs). */
    principalItems: { type: Array, default: () => [] },
    /** Usuarios únicos (para filtro de asignados). */
    uniqueUsers: { type: Array, default: () => [] },
    /** Prioridades únicas con icon_url. */
    uniquePriorities: { type: Array, default: () => [] },
    /** Tipos de actividad únicos. */
    uniqueTypes: { type: Array, default: () => [] },
    /** Informadores únicos (para filtro de reporter). */
    uniqueReporters: { type: Array, default: () => [] },
    /** Creadores únicos (para filtro de creator). */
    uniqueCreators: { type: Array, default: () => [] },
    /** Conteo de issues mostrados. */
    filteredCount: { type: Number, default: 0 },
    /** Total en backend. */
    totalCount: { type: Number, default: 0 },
    /** Filtros locales activos (cuenta). */
    activeLocalFilters: { type: Number, default: 0 },
    /** Spinner mientras aplica filtros. */
    tableLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'clear']);

// ── Tab activo del encabezado ────────────────────────────────────────────────
const activeTab = ref('atributos');

// ── Computed ─────────────────────────────────────────────────────────────────
// Conteo de filtros activos por sección (badge en cada tab).
const atributosActiveCount = computed(
    () =>
        (props.form.statuses?.length || 0) +
        (props.form.priorities?.length || 0) +
        (props.form.labels?.length || 0) +
        (props.localFilter.type ? 1 : 0) +
        (props.localFilter.impacto ? 1 : 0)
);

const personasActiveCount = computed(
    () =>
        (props.localFilter.assignee ? 1 : 0) +
        (props.localFilter.reporter ? 1 : 0) +
        (props.localFilter.creator ? 1 : 0)
);

const fechaActiveCount = computed(
    () => (props.localFilter.dateFrom || props.localFilter.dateTo ? 1 : 0)
);

const vistaActiveCount = computed(() => (props.form.group_by ? 1 : 0));

const totalActiveFilters = computed(
    () =>
        (props.form.statuses?.length || 0) +
        (props.form.priorities?.length || 0) +
        (props.activeLocalFilters || 0) +
        (props.form.group_by ? 1 : 0)
);

// ── Helpers ──────────────────────────────────────────────────────────────────
function toggleArrayValue(arr, value) {
    const i = arr.indexOf(value);
    if (i > -1) arr.splice(i, 1);
    else arr.push(value);
}

const initials = (name) =>
    (name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join('')
        .toUpperCase();

function onApply(closeDropdown) {
    emit('apply');
    closeDropdown?.();
}
</script>
