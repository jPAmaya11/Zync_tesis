<template>
    <div>
        <!-- Barra de herramientas unificada -->
        <div
            class="mb-4 bg-gradient-to-r from-slate-50 to-gray-50 border border-gray-200 rounded-lg shadow-sm p-2.5"
        >
            <div
                class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3"
            >
                <!-- Lado izquierdo: Información y controles -->
                <div class="flex items-center gap-4">
                    <!-- Información de registros con icono -->
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <div>
                            <div class="font-semibold text-gray-900 text-sm">
                                {{
                                    paginationInfo
                                        ? `${paginationInfo.from || 0}-${
                                              paginationInfo.to || 0
                                          }`
                                        : (rows || []).length.toLocaleString()
                                }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{
                                    paginationInfo
                                        ? `de ${(
                                              paginationInfo.total || 0
                                          ).toLocaleString()} registros`
                                        : (rows || []).length !== 1
                                        ? 'registros'
                                        : 'registro'
                                }}
                            </div>
                        </div>
                    </div>

                    <!-- Modo de selección simplificado -->
                    <div v-if="canAssign" class="flex items-center">
                        <div
                            class="flex items-center bg-white rounded-lg border border-gray-200 shadow-sm p-0.5"
                        >
                            <!-- Toggle de selección -->
                            <button
                                @click="toggleSelectionMode"
                                :class="[
                                    'flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-md transition-all duration-200',
                                    props.selectionMode
                                        ? 'bg-indigo-500 text-white shadow-sm'
                                        : 'text-gray-600 hover:bg-gray-100',
                                ]"
                                :title="
                                    props.selectionMode
                                        ? 'Desactivar selección'
                                        : 'Activar selección'
                                "
                            >
                                <span>Selección</span>
                            </button>

                            <!-- Controles de selección expandidos -->
                            <div
                                v-show="props.selectionMode"
                                class="flex items-center gap-1 ml-1 border-l border-gray-200 pl-2 transform transition-all duration-300"
                                :class="
                                    props.selectionMode
                                        ? 'opacity-100 scale-100'
                                        : 'opacity-0 scale-95'
                                "
                            >
                                <button
                                    @click="selectAll"
                                    class="px-2 py-1 text-xs text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 transition-colors"
                                    title="Seleccionar todo"
                                >
                                    Todo
                                </button>
                                <button
                                    @click="clearSelection"
                                    class="px-2 py-1 text-xs text-gray-600 bg-gray-50 rounded hover:bg-gray-100 transition-colors"
                                    title="Limpiar selección"
                                >
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Información de selección mejorada -->
                    <div
                        v-show="
                            canAssign &&
                            props.selectionMode &&
                            selectedCount > 0
                        "
                        class="flex items-center gap-1 px-2.5 py-1 bg-indigo-50 border border-indigo-200 rounded-md text-[11px] font-medium text-indigo-700"
                    >
                        <span>
                            {{ selectedCount }}
                        </span>
                        <span>
                            seleccionado{{ selectedCount !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <!-- Lado derecho: Controles de paginación sofisticados -->
                <div class="flex items-center gap-3">
                    <!-- Navegación de páginas mejorada -->
                    <div
                        class="flex items-center bg-white rounded-lg border border-gray-200 shadow-sm"
                    >
                        <button
                            @click="
                                () => {
                                    if (useInternalPagination) {
                                        page = 1;
                                    } else {
                                        $emit('page-change', 1);
                                    }
                                }
                            "
                            :disabled="page === 1"
                            class="flex items-center justify-center w-7 h-7 text-gray-500 hover:text-gray-700 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-200 rounded-l-lg"
                            title="Primera página"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7"
                                />
                            </svg>
                        </button>
                        <button
                            @click="handlePrevPage"
                            :disabled="page === 1"
                            class="flex items-center justify-center w-7 h-7 text-gray-500 hover:text-gray-700 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-200 border-l border-gray-200"
                            title="Anterior"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </button>

                        <!-- Selector de página sofisticado -->
                        <div
                            class="flex items-center px-2.5 border-l border-gray-200"
                        >
                            <Dropdown
                                align="left"
                                content-classes="bg-white dark:bg-gray-800"
                            >
                                <template #trigger>
                                    <button
                                        class="text-xs font-medium text-gray-700 bg-transparent border-none focus:outline-none px-2 py-1 rounded cursor-pointer"
                                        type="button"
                                        style="
                                            padding: 0px 15px 0px 15px !important;
                                        "
                                    >
                                        {{ page }}
                                    </button>
                                </template>

                                <template #content="{ search }">
                                    <div class="py-1">
                                        <button
                                            v-for="pageNum in totalPages"
                                            :key="pageNum"
                                            @click.stop="
                                                (() => {
                                                    const newPage = pageNum;
                                                    if (useInternalPagination) {
                                                        page = newPage;
                                                    } else {
                                                        $emit(
                                                            'page-change',
                                                            newPage
                                                        );
                                                    }
                                                })()
                                            "
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            {{ pageNum }}
                                        </button>
                                    </div>
                                </template>
                            </Dropdown>
                            <span class="text-xs text-gray-400 ml-1"
                                >/{{ totalPages }}</span
                            >
                        </div>

                        <button
                            @click="handleNextPage"
                            :disabled="page === totalPages"
                            class="flex items-center justify-center w-7 h-7 text-gray-500 hover:text-gray-700 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-200 border-l border-gray-200"
                            title="Siguiente"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </button>
                        <button
                            @click="
                                () => {
                                    if (useInternalPagination) {
                                        page = totalPages;
                                    } else {
                                        $emit('page-change', totalPages);
                                    }
                                }
                            "
                            :disabled="page === totalPages"
                            class="flex items-center justify-center w-7 h-7 text-gray-500 hover:text-gray-700 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all duration-200 border-l border-gray-200 rounded-r-lg"
                            title="Última página"
                        >
                            <svg
                                class="w-3.5 h-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 5l7 7-7 7M5 5l7 7-7 7"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Selector de elementos por página mejorado -->
                    <div v-if="showPerPageSelector" class="flex items-center">
                        <Dropdown
                            align="left"
                            :minWidth="'110px'"
                            content-classes="bg-white dark:bg-gray-800"
                        >
                            <template #trigger>
                                <button
                                    class="text-xs font-medium text-gray-700 bg-transparent border-none focus:outline-none px-3 py-2 rounded"
                                    type="button"
                                    style="
                                        padding: 0px 15px 0px 15px !important;
                                    "
                                >
                                    {{ currentPerPage }}
                                </button>
                            </template>
                            <template #content>
                                <div class="py-1">
                                    <button
                                        v-for="opt in perPageOptions"
                                        :key="opt"
                                        @click.stop="
                                            () =>
                                                handlePerPageChange({
                                                    target: { value: opt },
                                                })
                                        "
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                    >
                                        {{ opt }}
                                    </button>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </div>
        </div>
        <StandardTable
            :data="paginatedRows"
            :columns="columns"
            :containerClass="containerClass"
            :showSearch="false"
            :emptyMessage="emptyMessage"
            :emptyDescription="emptyDescription"
            :itemKey="idKey"
            :rowClass="rowClass"
            @row-click="$emit('row-click', $event)"
            @row-mousedown="$emit('row-mousedown', $event)"
            @row-mouseenter="$emit('row-mouseenter', $event)"
            @row-mouseup="$emit('row-mouseup', $event)"
        >
            <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
                <slot :name="slotName" v-bind="slotProps" />
            </template>
        </StandardTable>
    </div>
</template>

<script setup>
import Dropdown from '@/Components/Navigation/DropdownCustom.vue';
import { computed, ref, watch } from 'vue';
import StandardTable from './StandardTable.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    columns: { type: Array, required: true },
    idKey: { type: String, default: 'id' },
    pageSize: { type: Number, default: 10 },
    modelValue: { type: Number, default: 1 },
    containerClass: { type: String, default: '' },
    emptyMessage: { type: String, default: 'No hay registros' },
    emptyDescription: {
        type: String,
        default: 'No se encontraron elementos para mostrar',
    },
    showPagination: { type: Boolean, default: true },
    totalPages: { type: Number, default: 1 },
    useInternalPagination: { type: Boolean, default: true },
    // Props para información de paginación
    paginationInfo: { type: Object, default: null },
    // Props para control de registros por página
    showPerPageSelector: { type: Boolean, default: true },
    perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
    currentPerPage: { type: Number, default: 50 },
    // Props para selección múltiple
    rowClass: { type: Function, default: null },
    canAssign: { type: Boolean, default: false },
    // Props para controlar la selección desde el padre
    selectedItems: { type: [Array, Set], default: () => [] },
    selectionMode: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:modelValue',
    'row-click',
    'row-mousedown',
    'row-mouseenter',
    'row-mouseup',
    'page-change',
    'per-page-change',
    'update:selectionMode',
    'toggle-selection-mode',
    'select-all',
    'clear-selection',
]);

const page = ref(props.modelValue);

// Computed para el conteo de selección
const selectedCount = computed(() => {
    if (Array.isArray(props.selectedItems)) {
        return props.selectedItems.length;
    }
    if (props.selectedItems instanceof Set) {
        return props.selectedItems.size;
    }
    return 0;
});

// Funciones de selección que delegan al padre
const toggleSelectionMode = () => {
    emit('toggle-selection-mode');
};

const selectAll = () => {
    emit('select-all');
};

const clearSelection = () => {
    emit('clear-selection');
};

watch(
    () => props.modelValue,
    (val) => {
        page.value = val;
    }
);

watch(page, (val) => {
    // Solo emitir para paginación interna
    if (props.useInternalPagination) {
        emit('update:modelValue', val);
        emit('page-change', val);
    }
});

const totalPages = computed(() => {
    if (props.useInternalPagination) {
        return Math.ceil(props.rows.length / props.pageSize);
    }
    return props.totalPages;
});

const paginatedRows = computed(() => {
    if (props.useInternalPagination) {
        const start = (page.value - 1) * props.pageSize;
        return props.rows.slice(start, start + props.pageSize);
    }
    // Para paginación externa, mostrar todos los rows tal como vienen del backend
    return props.rows;
});

function prevPage() {
    if (page.value > 1) page.value--;
}
function nextPage() {
    if (page.value < totalPages.value) page.value++;
}
function handlePerPageChange(event) {
    emit('per-page-change', parseInt(event.target.value));
}

// Funciones para manejar paginación tanto interna como externa
function handlePrevPage() {
    if (props.useInternalPagination) {
        prevPage();
    } else {
        // Para paginación externa, emitir evento con la página anterior
        if (page.value > 1) {
            emit('page-change', page.value - 1);
        }
    }
}

function handleNextPage() {
    if (props.useInternalPagination) {
        nextPage();
    } else {
        // Para paginación externa, emitir evento con la página siguiente
        if (page.value < totalPages.value) {
            emit('page-change', page.value + 1);
        }
    }
}
</script>

<style scoped>
button[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
