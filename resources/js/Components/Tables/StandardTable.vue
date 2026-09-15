<template>
    <div class="table-wrapper overflow-auto" :class="containerClass">
        <div
            v-if="showSearch || $slots.header"
            class="p-4 border-b border-gray-100"
        >
            <slot name="header">
                <!-- Slot para contenido personalizado del header -->
            </slot>

            <!-- Búsqueda por defecto si se habilita -->
            <div v-if="showSearch" class="flex items-center gap-4">
                <div class="relative flex-1 max-w-sm">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full pl-10 pr-4 py-2 rounded border border-gray-200 bg-white shadow-sm text-sm placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none focus:border-indigo-300 transition"
                    />
                    <svg
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"
                        />
                    </svg>
                </div>
            </div>
        </div>

        <div>
            <table
                :class="[
                    bare
                        ? 'w-full border-collapse'
                        : flat
                          ? 'min-w-full border-collapse border border-gray-200 rounded-xl overflow-hidden'
                          : 'min-w-full divide-y divide-gray-200 border border-gray-100 shadow-sm',
                    striped ? 'st-striped' : '',
                    tableClass,
                ]"
            >
                <thead
                    :class="[
                        bare ? '' : flat ? 'bg-gray-50 border-b border-gray-200' : 'bgPrincipal',
                        theadClass,
                    ]"
                >
                    <tr :class="headRowClass">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            :class="thClasses(column)"
                            :style="column.thStyle || null"
                        >
                            <!-- Contenido de cabecera personalizable (p.ej. iconos de orden) -->
                            <slot
                                :name="`header-${column.key}`"
                                :column="column"
                            >
                                {{ column.label }}
                            </slot>
                        </th>
                    </tr>
                </thead>
                <tbody
                    :class="[
                        bare
                            ? ''
                            : flat
                              ? 'bg-white divide-y divide-gray-100'
                              : 'bg-white divide-y divide-gray-100 dark:bg-gray-900',
                        tbodyClass,
                    ]"
                >
                    <template
                        v-for="(item, index) in filteredData"
                        :key="getItemKey(item, index)"
                    >
                        <tr
                            :data-row-index="index"
                            :class="[
                                rowClass
                                    ? rowClass(item, index)
                                    : [
                                          'hover:bg-indigo-50/50 transition-all duration-200 group cursor-pointer',
                                          index % 2 === 0
                                              ? 'bg-white dark:bg-gray-900'
                                              : 'bg-gray-50/50 dark:bg-gray-800/80',
                                      ],
                                striped && index % 2 === 1 ? 'st-alt' : '',
                            ]"
                            :style="rowStyle ? rowStyle(item, index) : null"
                            @click="handleRowClick(item, $event, index)"
                            @mousedown="handleMouseDown(item, $event, index)"
                            @mouseenter="handleMouseEnter(item, $event, index)"
                            @mouseup="handleMouseUp(item, $event, index)"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                :class="tdClasses(column, item, index)"
                                :style="
                                    column.cellStyle
                                        ? column.cellStyle(item, index)
                                        : null
                                "
                            >
                                <!-- Slot dinámico para cada columna -->
                                <slot
                                    :name="`cell-${column.key}`"
                                    :item="item"
                                    :value="getNestedValue(item, column.key)"
                                    :index="index"
                                >
                                    <!-- Renderizado por defecto -->
                                    <component
                                        v-if="column.component"
                                        :is="column.component"
                                        v-bind="getComponentProps(column, item)"
                                    />
                                    <template v-else>
                                        {{
                                            formatValue(
                                                getNestedValue(
                                                    item,
                                                    column.key
                                                ),
                                                column,
                                                item
                                            )
                                        }}
                                    </template>
                                </slot>
                            </td>
                        </tr>

                        <!-- Fila expandible (detalles) -->
                        <tr v-if="$slots['row-details']" class="bg-white">
                            <td :colspan="columns.length" class="p-0">
                                <slot
                                    name="row-details"
                                    :item="item"
                                    :index="index"
                                ></slot>
                            </td>
                        </tr>

                        <!-- Filas crudas adicionales por cada item (p.ej. submétricas
                             desplegables que comparten las mismas columnas). El consumidor
                             aporta sus propios <tr>. -->
                        <slot name="row-append" :item="item" :index="index" />
                    </template>

                    <!-- Filas crudas al final del tbody (p.ej. fila Total). -->
                    <slot name="tbody-append" />

                    <!-- Estado vacío -->
                    <tr v-if="filteredData.length === 0">
                        <td :colspan="columns.length" class="text-center py-16">
                            <slot name="empty">
                                <div
                                    class="flex flex-col items-center justify-center text-gray-400"
                                >
                                    <div
                                        class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4"
                                    >
                                        <svg
                                            class="w-10 h-10 text-gray-300"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                    </div>
                                    <p
                                        class="text-lg font-semibold text-gray-600 mb-1"
                                    >
                                        {{ emptyMessage }}
                                    </p>
                                    <p class="text-sm text-gray-400">
                                        {{ emptyDescription }}
                                    </p>
                                </div>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer opcional -->
        <div
            v-if="$slots.footer"
            class="p-4 border-t border-gray-100 bg-gray-50"
        >
            <slot name="footer"></slot>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    // Datos de la tabla
    data: {
        type: Array,
        required: true,
        default: () => [],
    },

    // Configuración de columnas
    columns: {
        type: Array,
        required: true,
        validator: (columns) => {
            return columns.every((col) => col.key && col.label);
        },
    },

    // Búsqueda
    showSearch: {
        type: Boolean,
        default: false,
    },
    searchPlaceholder: {
        type: String,
        default: "Buscar...",
    },
    searchFields: {
        type: Array,
        default: () => [],
    },

    // Mensajes de estado vacío
    emptyMessage: {
        type: String,
        default: "No hay registros",
    },
    emptyDescription: {
        type: String,
        default: "No se encontraron elementos para mostrar",
    },

    // Clases personalizadas
    containerClass: {
        type: String,
        default: "",
    },

    // Función para obtener la key única de cada item
    itemKey: {
        type: [String, Function],
        default: "id",
    },

    // Función para clases dinámicas de fila
    rowClass: {
        type: Function,
        default: null,
    },

    // ── Extensiones OPCIONALES (no afectan el uso por defecto) ───────────────
    // `bare`: desactiva todo el "chrome" propio de la tabla (cabecera bgPrincipal,
    // paddings/colores/bordes por defecto) para que el consumidor controle el
    // look 100% vía column.thClass / column.cellClass / column.cellStyle y los
    // overrides de tableClass/theadClass/tbodyClass, sin perder el modelo de
    // columnas + slots.
    // `flat`: cabecera clara con borde en vez de la barra solida `bgPrincipal`,
    // sin sombra y con separadores finos. Sin esta prop se ve `default`.
    variant: {
        type: String,
        default: "default", // 'default' | 'bare' | 'flat'
    },
    // Cabecera fija al hacer scroll vertical.
    stickyHeader: {
        type: Boolean,
        default: false,
    },
    // Clases extra para <table>, <thead>, <tr> de cabecera y <tbody>.
    tableClass: { type: String, default: "" },
    theadClass: { type: String, default: "" },
    headRowClass: { type: String, default: "" },
    tbodyClass: { type: String, default: "" },
    // Estilo inline por fila (p.ej. variable --i para animaciones escalonadas).
    rowStyle: {
        type: Function,
        default: null,
    },
    // Zebra opt-in: fondo tenue alternado por fila (en celdas, para superar el
    // bg !important que la app aplica a `.dark tbody tr`). No afecta a las hojas
    // que no lo activan.
    striped: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    "search",
    "row-click",
    "row-mousedown",
    "row-mouseenter",
    "row-mouseup",
]);

// Estado interno
const searchQuery = ref("");

const bare = computed(() => props.variant === "bare");
const flat = computed(() => props.variant === "flat");

// Datos filtrados
const filteredData = computed(() => {
    if (!props.showSearch || !searchQuery.value.trim()) {
        return props.data;
    }

    const query = searchQuery.value.toLowerCase();
    const searchFields =
        props.searchFields.length > 0
            ? props.searchFields
            : props.columns.map((col) => col.key);

    return props.data.filter((item) => {
        return searchFields.some((field) => {
            const value = getNestedValue(item, field);
            return String(value || "")
                .toLowerCase()
                .includes(query);
        });
    });
});

// Funciones helper
function getNestedValue(obj, path) {
    return path.split(".").reduce((current, key) => current?.[key], obj);
}

function getItemKey(item, index) {
    if (typeof props.itemKey === "function") {
        return props.itemKey(item, index);
    }
    return getNestedValue(item, props.itemKey) || index;
}

// Alineación por columna (mismo mapeo en ambos variants).
function alignClass(column) {
    return column.align === "center"
        ? "text-center"
        : column.align === "right"
        ? "text-right"
        : "text-left";
}

// Clases de la celda de cabecera. En 'bare' no se imponen estilos propios:
// solo alineación + sticky opcional + column.thClass.
function thClasses(column) {
    const out = [];
    if (flat.value) {
        out.push(
            "px-3 py-2.5 text-[11px] font-bold text-gray-600 uppercase tracking-wider"
        );
    } else if (!bare.value) {
        out.push(
            "px-2 py-2.5 text-xs font-bold text-white uppercase tracking-wider"
        );
    }
    if (props.stickyHeader) out.push("sticky top-0 z-10");
    out.push(alignClass(column));
    if (column.thClass) out.push(column.thClass);
    return out;
}

// Clases de celda de cuerpo. `cellClass` admite string o función(item, index).
// En 'default' se conserva el padding/color por defecto; en 'bare' no.
function tdClasses(column, item, index) {
    const dynamic =
        typeof column.cellClass === "function"
            ? column.cellClass(item, index)
            : column.cellClass;
    if (bare.value) {
        return [alignClass(column), dynamic];
    }
    if (flat.value) {
        return [
            "px-3 py-2.5 text-sm whitespace-nowrap",
            alignClass(column),
            dynamic || "text-gray-700",
        ];
    }
    return [
        "px-2 py-2 text-sm whitespace-nowrap",
        alignClass(column),
        dynamic || "text-gray-900",
    ];
}

function formatValue(value, column, item) {
    // Formateador personalizado por columna (se ejecuta incluso con value nulo
    // para permitir placeholders propios tipo "—").
    if (typeof column.format === "function") {
        return column.format(value, item);
    }

    if (value == null) return "-";

    // Formateo personalizado por tipo
    switch (column.type) {
        case "date":
            if (!value) return "-";
            // Extraer solo YYYY-MM-DD (funciona para "YYYY-MM-DD" y "YYYY-MM-DD HH:mm:ss")
            const onlyDate = value.split(' ')[0].split('T')[0];
            const [year, month, day] = onlyDate.split('-').map(Number);
            // new Date(year, monthIndex, day) crea una fecha LOCAL
            return new Date(year, month - 1, day).toLocaleDateString("es-ES");
        case "datetime":
            return new Date(value).toLocaleString("es-ES");
        case "currency":
            return new Intl.NumberFormat("es-ES", {
                style: "currency",
                currency: "EUR",
            }).format(value);
        case "number":
            return new Intl.NumberFormat("es-ES").format(value);
        case "boolean":
            return value ? "Sí" : "No";
        default:
            return String(value);
    }
}

function getComponentProps(column, item) {
    if (typeof column.componentProps === "function") {
        return column.componentProps(item);
    }
    return {
        ...column.componentProps,
        [column.componentProp || "value"]: getNestedValue(item, column.key),
    };
}

// Función para manejar click en fila
function handleRowClick(item, event, index) {
    emit("row-click", { item, event, index, dataArray: filteredData.value });
}

// Funciones para drag selection
function handleMouseDown(item, event, index) {
    emit("row-mousedown", {
        item,
        event,
        index,
        dataArray: filteredData.value,
    });
}

function handleMouseEnter(item, event, index) {
    emit("row-mouseenter", {
        item,
        event,
        index,
        dataArray: filteredData.value,
    });
}

function handleMouseUp(item, event, index) {
    emit("row-mouseup", { item, event, index, dataArray: filteredData.value });
}
</script>

<style scoped>
/* Mejoras específicas para la tabla */
.group:hover .group-hover\:visible {
    visibility: visible;
}

.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

/* Transiciones suaves */
td,
th {
    transition: background-color 0.2s ease;
}

/* ── Zebra opt-in (prop `striped`) ──────────────────────────────────────────
   El fondo alterno se pinta en las CELDAS (> td), no en la fila, a propósito:
   la app aplica `.dark tbody tr { background !important }` globalmente, y al
   pintar el td (que va por encima del tr) lo superamos sin pelear con !important.
   El hover se define DESPUÉS para ganarle a la franja (misma especificidad). */
.st-striped tbody > tr.st-alt > td { background-color: #f6f7f9; }
.dark .st-striped tbody > tr.st-alt > td { background-color: rgba(255, 255, 255, 0.04); }

.st-striped tbody > tr:hover > td { background-color: #ECF3F7; }
.dark .st-striped tbody > tr:hover > td { background-color: rgba(255, 255, 255, 0.07); }
</style>
