<template>
    <div class="relative w-full overflow-hidden">
        <!-- Botón del Select -->
        <button
            ref="triggerRef"
            @click="toggleSelect"
            @keydown.down.prevent="focusNext"
            @keydown.up.prevent="focusPrev"
            @keydown.enter.prevent="selectFocused"
            @keydown.escape="isOpen = false"
            :class="['modalSelect', triggerClass]"
            :style="
                Object.assign(
                    {
                        borderColor: error
                            ? '#ef4444'
                            : 'var(--colorPrincipal)',
                    },
                    triggerStyle
                )
            "
        >
            <span
                :class="[
                    'truncate text-left',
                    compact ? '' : 'flex-1',
                    'inline-flex',
                    'items-center',
                ]"
            >
                <template v-if="selectedOption && selectedOption.iso">
                    <span
                        :class="['fi', `fi-${selectedOption.iso}`, 'mr-2']"
                        aria-hidden="true"
                    ></span>
                </template>
                <span v-if="!multiple">
                    {{
                        selectedOption?.display ||
                        selectedOption?.label ||
                        placeholder
                    }}
                </span>
                <span v-else>
                    {{
                        Array.isArray(modelValue) && modelValue.length > 0
                            ? modelValue.length === 1
                                ? options.find(o => o.value === modelValue[0])?.label || placeholder
                                : modelValue.length + ' seleccionados'
                            : placeholder
                    }}
                </span>
            </span>
            <svg
                :class="[
                    'w-4 h-4 transition-transform duration-300 flex-shrink-0 ml-2',
                    { 'rotate-180': isOpen },
                ]"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
                style="color: var(--colorPrincipal)"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M19 14l-7 7m0 0l-7-7m7 7V3"
                />
            </svg>
        </button>

        <!-- Dropdown flotante con Floating UI -->
        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="floatingRef"
                :style="{ ...floatingStyles, width: floatingWidth }"
                class="fixed bg-white dark:bg-gray-900 overflow-hidden border border-gray-300 dark:border-gray-600 rounded-lg shadow-xl z-[9999] border-1"
            >
                <!-- Buscador (opcional) -->
                <div
                    v-if="searchable"
                    class="border-b border-gray-200 dark:border-gray-700"
                >
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar..."
                        class="w-full bg-transparent text-sm text-gray-900 dark:text-gray-100 focus:outline-none placeholder-gray-400 dark:placeholder-gray-500 px-3 py-2"
                        @keydown.down.prevent="focusNext"
                        @keydown.up.prevent="focusPrev"
                        @keydown.enter.prevent="selectFocused"
                        style="border-radius: 8px 8px 0px 0px; border: none"
                    />
                </div>

                <!-- Lista de opciones -->
                <OverlayScrollbarsComponent
                    :options="{ scrollbars: { autoHide: 'move' } }"
                    class="os-host os-theme-light dark:os-theme-dark max-h-48"
                >
                    <div
                        v-if="filteredOptions.length === 0"
                        class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400 text-center"
                    >
                        No hay opciones
                    </div>
                    <button
                        v-for="(option, index) in filteredOptions"
                        :key="option.value"
                        @click="selectOption(option)"
                        @mouseover="focusedIndex = index"
                        :class="[
                            'w-full px-3 py-2 text-sm text-left transition-all flex items-center',
                            {
                                'bg-blue-500 text-white dark:bg-blue-600':
                                    focusedIndex === index,
                                'bg-blue-100 text-blue-900 dark:bg-blue-900 dark:text-blue-100':
                                    (multiple ? (Array.isArray(modelValue) && modelValue.includes(option.value)) : selectedOption?.value === option.value) &&
                                    focusedIndex !== index,
                                'bg-gray-50 text-gray-900 dark:bg-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-700':
                                    !(multiple ? (Array.isArray(modelValue) && modelValue.includes(option.value)) : selectedOption?.value === option.value) &&
                                    focusedIndex !== index,
                            },
                        ]"
                    >
                        <template v-if="option.iso">
                            <span
                                :class="['fi', `fi-${option.iso}`, 'mr-2']"
                                aria-hidden="true"
                            ></span>
                        </template>
                        <template v-if="multiple">
                            <div class="mr-2 flex items-center justify-center w-4 h-4 border rounded" :class="(Array.isArray(modelValue) && modelValue.includes(option.value)) ? 'bg-blue-500 border-blue-500 text-white' : 'border-gray-300 dark:border-gray-600'">
                                <svg v-if="Array.isArray(modelValue) && modelValue.includes(option.value)" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </template>
                        <span>{{ option.label }}</span>
                    </button>
                </OverlayScrollbarsComponent>
            </div>
        </Teleport>

        <!-- Error message -->
        <div v-if="error" class="text-red-600 text-xs mt-1">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import { useSelectManager } from '@/Composables/useSelectManager';
import { flip, offset, shift, size, useFloating } from '@floating-ui/vue';
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue';
import 'overlayscrollbars/overlayscrollbars.css';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Array],
        default: '',
    },
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Seleccionar...',
    },
    searchable: {
        type: Boolean,
        default: true,
    },
    error: {
        type: String,
        default: '',
    },
    triggerClass: {
        type: String,
        default: '',
    },
    triggerStyle: {
        type: [String, Object],
        default: () => ({}),
    },
    compact: {
        type: Boolean,
        default: false,
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    remote: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'search']);

// Usar el select manager
const { selectId, closeOtherSelects, activeSelectId, closeSelect } =
    useSelectManager();

const isOpen = ref(false);
const searchQuery = ref('');
const focusedIndex = ref(0);
const triggerRef = ref(null);
const floatingRef = ref(null);
const floatingWidth = ref('auto');

// Floating UI
const { floatingStyles, update } = useFloating(triggerRef, floatingRef, {
    middleware: [
        offset(8),
        flip(),
        shift({ padding: 8 }),
        size({
            apply({ rects }) {
                floatingWidth.value = `${rects.reference.width}px`;
            },
        }),
    ],
});

// Opciones filtradas
const filteredOptions = computed(() => {
    if (props.remote) return props.options;
    if (!searchQuery.value) return props.options;
    return props.options.filter((opt) =>
        opt.label.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Opción seleccionada
const selectedOption = computed(() => {
    return props.options.find((opt) => opt.value === props.modelValue);
});

// Métodos de navegación
const focusNext = () => {
    if (filteredOptions.value.length > 0) {
        focusedIndex.value =
            (focusedIndex.value + 1) % filteredOptions.value.length;
    }
};

const focusPrev = () => {
    if (filteredOptions.value.length > 0) {
        focusedIndex.value =
            (focusedIndex.value - 1 + filteredOptions.value.length) %
            filteredOptions.value.length;
    }
};

const selectFocused = () => {
    if (filteredOptions.value[focusedIndex.value]) {
        selectOption(filteredOptions.value[focusedIndex.value]);
    }
};

const selectOption = (option) => {
    if (props.multiple) {
        const arr = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
        const index = arr.indexOf(option.value);
        if (index > -1) arr.splice(index, 1);
        else arr.push(option.value);
        emit('update:modelValue', arr);
    } else {
        emit('update:modelValue', option.value);
        isOpen.value = false;
        closeSelect(selectId);
        searchQuery.value = '';
        focusedIndex.value = 0;
    }
};

// Cerrar al hacer click fuera
const handleClickOutside = (e) => {
    if (
        triggerRef.value &&
        floatingRef.value &&
        !triggerRef.value.contains(e.target) &&
        !floatingRef.value.contains(e.target)
    ) {
        isOpen.value = false;
        closeSelect(selectId);
    }
};

// Watch para actualizar posición cuando cambia el dropdown
watch(activeSelectId, (newVal) => {
    if (newVal === selectId) {
        // Este select debe abrirse
        isOpen.value = true;
        update();
        setTimeout(() => update(), 0);
        document.addEventListener('click', handleClickOutside);
    } else {
        // Otro select se abrió, cerrar este
        isOpen.value = false;
        document.removeEventListener('click', handleClickOutside);
        searchQuery.value = '';
    }
});

watch(searchQuery, (newVal) => {
    emit('search', newVal);
});

// Manejar click en el botón del select
const toggleSelect = () => {
    if (isOpen.value) {
        isOpen.value = false;
        closeSelect(selectId);
    } else {
        closeOtherSelects();
    }
};

// Animaciones
const onEnter = (el) => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px)';
    el.offsetHeight; // trigger reflow
    el.style.transition = 'all 0.2s ease-out';
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
};

const onLeave = (el) => {
    el.style.transition = 'all 0.2s ease-in';
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px)';
};

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.dropdown-enter-active {
    transition: all 0.2s ease-out;
}

.dropdown-leave-active {
    transition: all 0.2s ease-in;
}

.dropdown-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
