<template>
    <span
        :class="badgeClass"
        class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold tracking-wide uppercase border shadow-sm"
    >
        {{ statusLabel }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: String,
    config: Object,
    active: Boolean,
});

const tieneStatus = computed(
    () => props.status && props.status !== 'undefined' && props.status !== ''
);

const statusLabel = computed(() => {
    if (tieneStatus.value) {
        return props.config?.[props.status]?.label ?? props.status;
    }

    if (props.active !== undefined) {
        return props.active ? 'Activo' : 'Inactivo';
    }

    return 'Desconocido';
});

const badgeClass = computed(() => {
    if (tieneStatus.value) {
        const color = props.config?.[props.status]?.color;
        return color
            ? `bg-${color}-50 text-${color}-700 border-${color}-200`
            : 'bg-gray-50 text-gray-600 border-gray-200';
    }

    if (props.active !== undefined) {
        return props.active
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
            : 'bg-red-50 text-red-700 border-red-200';
    }

    return 'bg-gray-50 text-gray-600 border-gray-200';
});
</script>
