<template>
    <div class="relative" v-if="hasOperations">
        <Dropdown :align="align" :width="width">
            <template #trigger>
                <button
                    class="flex items-center gap-2 bgPrincipal text-white px-5 py-2.5 rounded-lg shadow-sm hover:bg-indigo-700 transition-colors duration-200 h-[44px]"
                    :class="[
                        triggerClass,
                        {
                            'opacity-75 cursor-not-allowed': disabled,
                        },
                    ]"
                    :disabled="disabled"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                        />
                    </svg>
                    <span>{{ title }}</span>
                    <svg
                        class="w-4 h-4 transition-transform"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </button>
            </template>

            <template #content>
                <div class="py-1">
                    <!-- Slot para operaciones personalizadas -->
                    <slot name="operations" />

                    <!-- Operaciones por defecto usando DropdownButton -->
                    <template
                        v-for="operation in operations"
                        :key="operation.id"
                    >
                        <DropdownButton
                            v-if="operation.show"
                            @click="handleOperationClick(operation)"
                            :active="false"
                            :disabled="operation.disabled"
                        >
                            <div class="flex items-center gap-3">
                                <!-- Icono -->
                                <svg
                                    class="w-5 h-5 flex-shrink-0"
                                    :class="getIconClasses(operation)"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :d="operation.icon || 'M12 4v16m8-8H4'"
                                    />
                                </svg>

                                <!-- Texto -->
                                <span class="font-medium">
                                    {{ operation.label }}
                                </span>
                            </div>
                        </DropdownButton>
                    </template>
                </div>
            </template>
        </Dropdown>
    </div>
</template>

<script setup>
import DropdownButton from '@/Components/Buttons/DropdownButton.vue';
import Dropdown from '@/Components/Navigation/DropdownCustom.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Operaciones',
    },
    operations: {
        type: Array,
        default: () => [],
    },
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '64',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    badgeCount: {
        type: Number,
        default: 0,
    },
    triggerClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['operation-click']);

const isOpen = ref(false);

// Computed para verificar si hay operaciones disponibles
const hasOperations = computed(() => {
    return (
        props.operations.some((op) => op.show) ||
        !!document.querySelector('slot[name="operations"]')
    );
});

// Manejar click en operación
const handleOperationClick = (operation) => {
    if (operation.disabled) return;

    emit('operation-click', operation.id);

    if (operation.action && typeof operation.action === 'function') {
        operation.action();
    }
};

// Clases para iconos según variante
const getIconClasses = (operation) => {
    const colorMap = {
        success: 'text-green-600',
        primary: 'text-blue-600',
        warning: 'text-yellow-600',
        danger: 'text-red-600',
        purple: 'text-purple-600',
    };

    return colorMap[operation.variant] || 'text-gray-500';
};
</script>
