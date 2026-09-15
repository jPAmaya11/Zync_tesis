<template>
    <ScrumModalOverlay :show="show" />
    <ModalView
        panel-class="gp-modal"
        :show="show"
        :title="isCreate ? 'Crear Espacio' : 'Editar Espacio'"
        size="2xl"
        :hide-footer="true"
        @close="close"
    >
        <template #subtitle>
            <template v-if="isCreate">Define un nuevo espacio de trabajo</template>
            <template v-else>
                <span>{{ project?.name }}</span>
                <span class="font-mono bg-white/20 px-1.5 py-0.5 rounded text-[10px] ml-1.5">{{ project?.key }}</span>
            </template>
        </template>

        <!-- Body con tabs (Datos / Equipo) -->
        <div class="space-y-4">
            <!-- ── Barra de secciones (tabs de subrayado) ── -->
            <div class="flex items-end border-b border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    @click="activeTab = 'datos'"
                    :class="['px-4 py-2.5 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-2', activeTab === 'datos' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                >
                    Datos
                    <span v-if="hasDatosError" class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'equipo'"
                    :class="['px-4 py-2.5 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap flex items-center gap-2', activeTab === 'equipo' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                >
                    Equipo
                    <span v-if="hasEquipoError" class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                </button>
                <!-- Destructivo: eliminar espacio (solo edición + permiso) -->
                <button
                    v-if="!isCreate && canDelete"
                    type="button"
                    @click="activeTab = 'destructivo'"
                    :class="['px-4 py-2.5 text-md font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap inline-flex items-center gap-1.5', activeTab === 'destructivo' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300']"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Destructivo
                </button>
            </div>

            <!-- ════ TAB: DATOS (nombre, clave, ícono, zona de peligro) ════ -->
            <div v-show="activeTab === 'datos'" class="space-y-5">
                <!-- Nombre + Clave (grid 2 cols) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input
                            ref="nameInputRef"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej: Proyecto ERP"
                            maxlength="150"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                            :class="{ 'border-red-400 focus:ring-red-400/40': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                            Clave
                            <span v-if="isCreate" class="text-red-500">*</span>
                        </label>
                        <input
                            v-if="isCreate"
                            v-model="form.key"
                            @input="onKeyInput"
                            type="text"
                            placeholder="ERP"
                            maxlength="5"
                            class="w-full px-3 py-2.5 text-sm font-mono uppercase text-center rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition tracking-widest"
                            :class="{ 'border-red-400 focus:ring-red-400/40': form.errors.key }"
                        />
                        <input
                            v-else
                            :value="project?.key"
                            type="text"
                            readonly
                            class="w-full px-3 py-2.5 text-sm font-mono uppercase text-center rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 text-gray-500 dark:text-gray-400 cursor-not-allowed tracking-widest"
                            title="La clave del proyecto no se puede editar"
                        />
                        <p v-if="form.errors.key" class="mt-1 text-xs text-red-500">
                            {{ form.errors.key }}
                        </p>
                    </div>
                </div>

                <!-- Descripción del espacio (opcional) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                        Descripción 
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        placeholder="Propósito del Espacio"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition resize-none"
                        :class="{ 'border-red-400 focus:ring-red-400/40': form.errors.description }"
                    ></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-500">
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Ícono del espacio (drag & drop y click para seleccionar) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                        Ícono Representativo
                    </label>
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="onFileSelected"
                    />
                    <div
                        v-on="iconDrop.handlers"
                        @click="triggerFileSelect"
                        :class="[
                            'relative flex flex-col items-center justify-center gap-2 rounded-lg transition-all select-none border-2 border-dashed',
                            !iconDrop.iconValue.value ? 'cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500' : 'cursor-default',
                            iconDrop.dragOver.value
                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                : 'border-gray-300 dark:border-gray-600 bg-gray-50/60 dark:bg-gray-800/60',
                            iconDrop.iconValue.value ? 'py-3' : 'py-6',
                        ]"
                    >
                        <template v-if="iconDrop.iconValue.value">
                            <img
                                v-if="iconDrop.previewUrl.value"
                                :src="iconDrop.previewUrl.value"
                                class="w-14 h-14 rounded-lg object-contain"
                            />
                            <span v-else class="text-4xl leading-none">{{ iconDrop.iconValue.value }}</span>
                            <button
                                type="button"
                                @click.stop="iconDrop.clear()"
                                class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:bg-red-100 hover:text-red-500 transition"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </template>
                        <template v-else>
                            <div
                                class="w-10 h-10 rounded-lg flex items-center justify-center"
                                :class="iconDrop.dragOver.value ? 'bg-indigo-100 dark:bg-indigo-800/40' : 'bg-gray-100 dark:bg-gray-700'"
                            >
                                <svg
                                    class="w-5 h-5"
                                    :class="iconDrop.dragOver.value ? 'text-indigo-500' : 'text-gray-400 dark:text-gray-500'"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                            </div>
                            <div class="text-center">
                                <p
                                    class="text-xs font-medium"
                                    :class="iconDrop.dragOver.value ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400'"
                                >
                                    {{ iconDrop.dragOver.value ? 'Suelta aquí' : 'Haz clic o arrastra un emoji o imagen' }}
                                </p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">PNG, JPG, SVG o emoji</p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Categoría (oculta por ahora) -->
                <div>
                    <!-- <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                        Categoría
                    </label> -->
                    <!-- <select
                        v-if="spaceCategories.length > 0"
                        v-model="form.space_category_id"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                    >
                        <option :value="null">— Sin categoría —</option>
                        <option v-for="cat in spaceCategories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                    <input
                        v-else
                        v-model="form.categoria"
                        type="text"
                        placeholder="Ej: Desarrollo, Operaciones, Marketing…"
                        maxlength="100"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                    /> -->
                </div>

                <!-- Bloquear fechas anteriores: switch por-espacio que controla el piso de la Fecha de Inicio -->
                <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Bloquear fechas anteriores</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                La Fecha de Inicio no podrá ser anterior a <strong>hoy</strong> (en actividades) ni al inicio de la <strong>actividad padre</strong> (en subactividades y reprogramaciones). Apágalo para permitir cualquier fecha de inicio.
                            </p>
                        </div>
                        <Switchtoggle v-model="form.validar_fechas_inicio" size="md" class="mt-0.5 shrink-0" />
                    </div>
                </div>

                <!-- Producción editable tras finalizar: switch por-espacio (relaja el candado terminal de fecha_aprobacion) -->
                <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Producción editable tras finalizar</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                Permite corregir la fecha de <strong>Producción</strong> (edición inline) aunque la actividad ya esté <strong>Finalizada</strong>. Apagado, ese campo queda bloqueado al finalizar (comportamiento por defecto).
                            </p>
                        </div>
                        <Switchtoggle v-model="form.produccion_editable_finalizado" size="md" class="mt-0.5 shrink-0" />
                    </div>
                </div>

            </div>

            <!-- ════ TAB: EQUIPO (propietario + equipos de trabajo) ════ -->
            <div v-show="activeTab === 'equipo'" class="space-y-5">
                <!-- Propietario -->
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Responsables del Espacio</p>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">
                        Propietario <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <UserAvatar
                            v-if="form.owner_id"
                            :src="findUser(form.owner_id)?.avatar_url"
                            :name="findUser(form.owner_id)?.display_name"
                            size="lg"
                        />
                        <div class="flex-1 min-w-0">
                            <SmartSelect
                                v-model="form.owner_id"
                                :options="userOptions"
                                placeholder="Buscar propietario…"
                                :searchable="true"
                                :remote="true"
                                @search="onOwnerSearch"
                                :error="form.errors.owner_id"
                            />
                        </div>
                    </div>
                </div>

                <!-- Equipos de Trabajo (selector de chips toggle) -->
                <div class="border-t border-gray-100 dark:border-gray-700 pt-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-500">Equipos de Trabajo</p>
                        <span
                            v-if="form.team_ids.length"
                            class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-0.5 rounded-full"
                        >{{ form.team_ids.length }} seleccionado{{ form.team_ids.length === 1 ? '' : 's' }}</span>
                    </div>

                    <template v-if="allTeams.length">
                        <!-- Buscador (solo si hay muchos equipos) -->
                        <div v-if="allTeams.length > 6" class="relative mb-2">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                            </svg>
                            <input
                                v-model="teamSearch"
                                type="text"
                                placeholder="Buscar equipo…"
                                class="w-full h-9 pl-8 pr-3 text-sm rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition"
                            />
                        </div>

                        <!-- Chips de equipos: clic para asignar/quitar -->
                        <div class="flex flex-wrap gap-2 max-h-44 overflow-y-auto p-0.5 custom-scrollbar">
                            <button
                                v-for="t in filteredTeams"
                                :key="t.id"
                                type="button"
                                @click="toggleTeam(t.id)"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-semibold rounded-lg border transition-colors duration-150',
                                    isTeamSelected(t.id)
                                        ? 'bg-indigo-600 text-white border-indigo-500 shadow-sm'
                                        : 'bg-gray-50 dark:bg-zinc-800/50 text-gray-600 dark:text-zinc-300 border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-700 hover:border-gray-300',
                                ]"
                            >
                                <svg
                                    v-if="isTeamSelected(t.id)"
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="3"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg
                                    v-else
                                    class="w-3.5 h-3.5 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                {{ t.name }}
                                <span
                                    v-if="t.is_active === false"
                                    class="ml-1 text-[9px] font-bold uppercase opacity-70"
                                >· inactivo</span>
                            </button>
                            <p v-if="!filteredTeams.length" class="text-xs text-gray-400 dark:text-gray-500 italic px-1 py-2">
                                No hay equipos que coincidan con la búsqueda.
                            </p>
                        </div>
                    </template>

                    <p v-else class="text-xs text-gray-400 dark:text-gray-500 italic py-2">
                        No hay equipos creados todavía.
                    </p>

                    <p class="mt-2 text-[10px] text-gray-400 italic">
                        Puedes asignar equipos ya creados o gestionar nuevos desde la cabecera del espacio una vez creado.
                    </p>
                </div>
            </div>

            <!-- ════ TAB: DESTRUCTIVO (eliminar espacio) ════ -->
            <div v-if="!isCreate && canDelete" v-show="activeTab === 'destructivo'" class="space-y-5">
                <div class="rounded-xl border border-rose-200 dark:border-rose-800/50 bg-rose-50/60 dark:bg-rose-900/10 p-4">
                    <p class="text-sm font-bold text-rose-700 dark:text-rose-300">Eliminar espacio</p>
                    <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-0.5 max-w-md">
                        Acción irreversible. Las tareas dentro <strong>no se eliminarán</strong>, pero el espacio
                        dejará de estar disponible.
                    </p>

                    <template v-if="!confirmDelete">
                        <button
                            type="button"
                            @click="confirmDelete = true"
                            class="mt-3 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Eliminar espacio
                        </button>
                    </template>
                    <template v-else>
                        <p class="text-sm font-semibold text-rose-700 dark:text-rose-300 mt-3 mb-2">
                            ¿Confirmas que quieres eliminar <em>{{ project?.name }}</em>?
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="onDelete"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-50 transition"
                            >
                                <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                Sí, eliminar
                            </button>
                            <button
                                type="button"
                                @click="confirmDelete = false"
                                class="px-3 py-2 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            >
                                Cancelar
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Footer del modal (botones primary actions) -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <button
                    type="button"
                    @click="close"
                    class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    @click="onSave"
                    :disabled="!canSubmit"
                    class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-sm"
                >
                    <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    {{ submitLabel }}
                </button>
            </div>
        </div>
    </ModalView>
</template>

<script setup>
import SmartSelect from '@/Components/Common/SmartSelect.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import Switchtoggle from '@/Components/Inputs/Switchtoggle.vue';
import ModalView from '@/Components/Modals/ModalView.vue';
import ScrumModalOverlay from '@/Components/Modules/GestionProyectos/ScrumModalOverlay.vue';
import { useProjectIconDrop } from '@/Composables/GestionProyectos/useProjectIconDrop';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create', validator: (v) => ['create', 'edit'].includes(v) },
    project: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    userOptions: { type: Array, default: () => [] },
    findUser: { type: Function, required: true },
    spaceCategories: { type: Array, default: () => [] },
    allTeams: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:show', 'saved', 'deleted', 'error', 'search-users']);

const page = usePage();
const isCreate = computed(() => props.mode === 'create');

// Tab activo del modal (Datos / Equipo).
const activeTab = ref('datos');

const form = useForm({
    name: '',
    key: '',
    description: '',
    icon: '',
    categoria: '',
    space_category_id: null,
    owner_id: null,
    team_ids: [],
    // Switch "Bloquear fechas anteriores" (piso de Fecha de Inicio al crear/reprogramar).
    validar_fechas_inicio: true,
    // Switch "Producción editable tras finalizar" (editar fecha_aprobacion aun Finalizado).
    produccion_editable_finalizado: false,
});

const iconDrop = useProjectIconDrop('');
const confirmDelete = ref(false);
const nameInputRef = ref(null);
const fileInputRef = ref(null);

// ── Indicadores de error por pestaña (guían al usuario tras un submit fallido) ──
const hasDatosError = computed(() => !!(form.errors.name || form.errors.key));
const hasEquipoError = computed(() => !!(form.errors.owner_id || form.errors.team_ids));

// ── Selector de equipos (chips toggle) ─────────────────────────────────────────
const teamSearch = ref('');
const filteredTeams = computed(() => {
    const q = teamSearch.value.trim().toLowerCase();
    // Equipos inactivos NO se ofrecen para asignar, salvo los que YA estén asignados
    // a este espacio (form.team_ids) → se conservan visibles para no perder la asignación.
    let list = props.allTeams.filter(
        (t) => t.is_active !== false || form.team_ids.includes(t.id)
    );
    if (q) list = list.filter((t) => (t.name || '').toLowerCase().includes(q));
    return list;
});
function isTeamSelected(id) {
    return form.team_ids.includes(id);
}
function toggleTeam(id) {
    const i = form.team_ids.indexOf(id);
    if (i > -1) form.team_ids.splice(i, 1);
    else form.team_ids.push(id);
}

function triggerFileSelect() {
    if (!iconDrop.iconValue.value) {
        fileInputRef.value?.click();
    }
}

function onFileSelected(e) {
    const file = e.target.files?.[0];
    if (file) {
        iconDrop.handleFile(file);
    }
}

watch(() => iconDrop.iconValue.value, (v) => { form.icon = v ?? ''; });

watch(() => form.name, (val) => {
    if (!isCreate.value) return;
    if (!val) {
        form.key = '';
        return;
    }
    form.key = val.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 5);
});

function onKeyInput(e) {
    form.key = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 5);
}

let searchTimeout = null;
function onOwnerSearch(query) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        emit('search-users', query);
    }, 300);
}

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
    if (val) {
        confirmDelete.value = false;
        activeTab.value = 'datos';
        teamSearch.value = '';
        if (isCreate.value) {
            form.reset();
            form.owner_id = page.props.auth?.user?.id ?? null;
            iconDrop.setIcon('');
            nextTick(() => nameInputRef.value?.focus());
        } else if (props.project) {
            form.name = props.project.name || '';
            form.key = props.project.key || '';
            form.description = props.project.description || '';
            form.categoria = props.project.categoria || '';
            form.space_category_id = props.project.space_category_id ?? null;
            form.owner_id =
                props.project.owner?.account_id ??
                page.props.auth?.user?.id ??
                null;
            form.team_ids = props.project.teams ? props.project.teams.map(t => t.id) : [];
            form.validar_fechas_inicio = props.project.validar_fechas_inicio ?? true;
            form.produccion_editable_finalizado = props.project.produccion_editable_finalizado ?? false;
            iconDrop.setIcon(props.project.icon || '');
        }
    } else {
        form.reset();
        form.clearErrors();
        iconDrop.setIcon('');
    }
});

const canSubmit = computed(() => {
    if (form.processing) return false;
    if (!form.name) return false;
    if (!form.owner_id) return false;
    if (isCreate.value && !form.key) return false;
    return true;
});

const submitLabel = computed(() => {
    if (form.processing) return isCreate.value ? 'Creando…' : 'Guardando…';
    return isCreate.value ? 'Crear Espacio' : 'Guardar cambios';
});

function close() {
    emit('update:show', false);
}

// Tras un error de validación, salta a la pestaña que contiene el primer error.
function focusErrorTab() {
    if (hasDatosError.value) activeTab.value = 'datos';
    else if (hasEquipoError.value) activeTab.value = 'equipo';
}

function onSave() {
    if (!canSubmit.value) return;

    const payload = (data) => ({
        name: data.name,
        description: data.description?.trim() || null,
        ...(isCreate.value ? { key: data.key } : {}),
        icon: data.icon || null,
        ...(props.spaceCategories.length > 0
            ? { space_category_id: data.space_category_id || null }
            : { categoria: data.categoria || null }),
        owner_id: data.owner_id ? parseInt(data.owner_id) : null,
        team_ids: Array.isArray(data.team_ids) ? data.team_ids : (data.team_ids ? [data.team_ids] : []),
        validar_fechas_inicio: !!data.validar_fechas_inicio,
        produccion_editable_finalizado: !!data.produccion_editable_finalizado,
    });

    if (isCreate.value) {
        form.transform(payload).post(route('gestion-proyectos.projects.store'), {
            onSuccess: () => {
                emit('saved', form.key); // key del espacio recién creado → para seleccionarlo
                close();
            },
            onError: (errors) => {
                focusErrorTab();
                emit('error', Object.values(errors)[0] ?? 'Error al crear el espacio.');
            },
        });
    } else {
        form.transform(payload).patch(
            route('gestion-proyectos.projects.update', props.project.key),
            {
                onSuccess: () => {
                    emit('saved');
                    close();
                },
                onError: (errors) => {
                    focusErrorTab();
                    emit('error', Object.values(errors)[0] ?? 'Error al actualizar el espacio.');
                },
            }
        );
    }
}

function onDelete() {
    if (!props.project) return;
    form.delete(route('gestion-proyectos.projects.destroy', props.project.key), {
        onSuccess: () => {
            emit('deleted');
            close();
        },
        onError: (errors) => {
            emit('error', Object.values(errors)[0] ?? 'Error al eliminar el espacio.');
        },
    });
}
</script>
