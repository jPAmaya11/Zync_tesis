<template>
    <div class="space-y-2">
        <!-- Un acordeón por cada módulo -->
        <div
            v-for="(group, gIdx) in groups"
            :key="group.id"
            class="rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
            <!-- ══ Cabecera del módulo ══ -->
            <div
                @click="toggleGroup(gIdx)"
                class="flex items-center justify-between cursor-pointer px-3 py-2.5 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors"
            >
                <div class="flex items-center gap-2.5">
                    <svg
                        class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform duration-200"
                        :class="{ 'rotate-90': openGroup === gIdx }"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ group.nombre }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Comentario/descripción del grupo (para qué sirve este módulo de permisos).
                         Solo en grupos que son un módulo real (moduleId != null). Lo lee cualquiera;
                         lo edita solo admin (el botón siempre abre; la escritura la controla el modal). -->
                    <button
                        v-if="group.moduleId != null"
                        type="button"
                        @click.stop="$emit('open-comment', { moduleId: group.moduleId, nombre: group.nombre })"
                        :title="hasComment(group)
                            ? 'Ver/editar la descripción de este grupo'
                            : (canComment ? 'Agregar una descripción' : 'Sin descripción')"
                        class="relative inline-flex items-center justify-center w-6 h-6 rounded-md transition-colors"
                        :class="hasComment(group)
                            ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-900/40 hover:bg-indigo-200 dark:hover:bg-indigo-900/60'
                            : 'text-gray-400 dark:text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700'"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <span v-if="hasComment(group)" class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    </button>
                    <span
                        class="text-xs rounded-full px-2 py-0.5 font-medium transition-colors"
                        :class="groupIsComplete(group)
                            ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                    >
                        {{ groupSelectedCount(group) }}/{{ group.permisos.length }}
                    </span>
                    <button
                        type="button"
                        @click.stop="toggleAllInGroup(group)"
                        class="text-xs font-medium rounded px-2 py-1 transition-colors"
                        :class="groupIsComplete(group)
                            ? 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 hover:bg-red-200'
                            : 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-200'"
                    >
                        {{ groupIsComplete(group) ? 'Quitar' : 'Todos' }}
                    </button>
                </div>
            </div>

            <!-- ══ Contenido del módulo: grid plano con subgrupos ══ -->
            <Transition name="accordion" @enter="onEnter" @leave="onLeave" @after-enter="afterEnter">
                <div v-if="openGroup === gIdx" class="accordion-content">
                    <div class="px-3 py-3 border-t border-gray-100 dark:border-gray-700 space-y-1">
                        <div v-if="getPermsWithoutSubgroup(group).length" class="mb-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                                <div
                                    v-for="perm in getPermsWithoutSubgroup(group)"
                                    :key="perm.name"
                                    @click="handleToggle(perm.name)"
                                    class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-md border cursor-pointer transition-all duration-150"
                                    :class="modelValue.includes(perm.name)
                                        ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-700'
                                        : 'bg-white dark:bg-gray-700/30 border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                                >
                                    <span class="text-xs text-gray-700 dark:text-gray-300 truncate flex-1">{{ extraerAccion(perm.name) }}</span>
                                    <Switchtoggle :model-value="modelValue.includes(perm.name)" size="sm" @update:model-value="handleToggle(perm.name)" @click.stop />
                                </div>
                            </div>
                        </div>
                        <div
                            v-for="subgroup in getSubgroups(group)"
                            :key="subgroup"
                            class="mb-3 last:mb-0"
                        >
                            <h4 class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 mb-1.5 uppercase tracking-wider">
                                {{ formatSubgroup(subgroup) }}
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                                <div
                                    v-for="perm in getPermsBySubgroup(group, subgroup)"
                                    :key="perm.name"
                                    @click="handleToggle(perm.name)"
                                    class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-md border cursor-pointer transition-all duration-150"
                                    :class="modelValue.includes(perm.name)
                                        ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-700'
                                        : 'bg-white dark:bg-gray-700/30 border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                                >
                                    <span class="text-xs text-gray-700 dark:text-gray-300 truncate flex-1">{{ extraerAccion(perm.name) }}</span>
                                    <Switchtoggle :model-value="modelValue.includes(perm.name)" size="sm" @update:model-value="handleToggle(perm.name)" @click.stop />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Resumen -->
        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Permisos seleccionados</span>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ modelValue.length }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Switchtoggle from '@/Components/Inputs/Switchtoggle.vue';

// ── Props ──────────────────────────────────────────────
const props = defineProps({
    groups: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: Array,
        default: () => [],
    },
    // Descripciones por módulo: mapa { [module_id]: { body, editor, updated_at } }.
    moduleComments: {
        type: Object,
        default: () => ({}),
    },
    // ¿El usuario actual (admin) puede escribir descripciones? (solo afecta el tooltip)
    canComment: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'open-comment']);

// ¿Este grupo/módulo ya tiene una descripción guardada?
function hasComment(group) {
    return group.moduleId != null && !!props.moduleComments[group.moduleId];
}

// ── Estado ──────────────────────────────────────────────
const openGroup = ref(null);

function toggleGroup(idx) {
    openGroup.value = openGroup.value === idx ? null : idx;
}

// ── Subgrupos planos ────────────────────────────────────

function getSubgroup(permName) {
    const parts = permName.split('.');
    return parts.length > 2 ? parts[1] : null;
}

function getSubgroups(group) {
    const subs = new Set();
    group.permisos.forEach((p) => {
        const sg = getSubgroup(p.name);
        if (sg) subs.add(sg);
    });
    return Array.from(subs).sort();
}

function getPermsBySubgroup(group, subgroup) {
    return group.permisos.filter((p) => getSubgroup(p.name) === subgroup);
}

function getPermsWithoutSubgroup(group) {
    return group.permisos.filter((p) => !getSubgroup(p.name));
}

function formatSubgroup(sg) {
    return sg.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function extraerAccion(permName) {
    const parts = permName.split('.');
    const accion = parts[parts.length - 1];
    return accion.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

// ── Contadores para la cabecera del módulo ──────────────
function groupSelectedCount(group) {
    return group.permisos.filter((p) => props.modelValue.includes(p.name)).length;
}

function groupIsComplete(group) {
    return group.permisos.length > 0 && groupSelectedCount(group) === group.permisos.length;
}

// ── Toggle individual de un permiso ─────────────────────
function handleToggle(permName) {
    const set = new Set(props.modelValue);
    if (set.has(permName)) {
        set.delete(permName);
    } else {
        set.add(permName);
    }
    emit('update:modelValue', Array.from(set));
}

// ── Toggle ALL del módulo ────────────────────────────────
function toggleAllInGroup(group) {
    const allPerms = group.permisos.map((p) => p.name);
    const allOn    = allPerms.every((p) => props.modelValue.includes(p));
    let current    = [...props.modelValue];

    if (allOn) {
        current = current.filter((v) => !allPerms.includes(v));
    } else {
        allPerms.forEach((p) => { if (!current.includes(p)) current.push(p); });
    }
    emit('update:modelValue', current);
}

// ── Acordeón ─────────────────────────────────────────────
function onEnter(el) {
    el.style.height = '0';
    el.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => {
        el.style.height = el.scrollHeight + 'px';
    }));
}
function afterEnter(el) {
    el.style.height = '';
    el.style.overflow = '';
}
function onLeave(el) {
    el.style.height = el.scrollHeight + 'px';
    el.style.overflow = 'hidden';
    el.getBoundingClientRect();
    requestAnimationFrame(() => { el.style.height = '0'; });
}
</script>

<style scoped>
.accordion-enter-active { transition: height 0.25s ease-out; overflow: hidden; }
.accordion-leave-active  { transition: height 0.2s ease-in;  overflow: hidden; }
.accordion-enter-from,
.accordion-leave-to      { height: 0 !important; }
.accordion-content       { overflow: hidden; }
</style>
