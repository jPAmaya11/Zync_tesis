<script setup>
const props = defineProps({
    active: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <button
        @click="!props.disabled && $emit('click')"
        type="button"
        class="flex items-center w-full px-4 py-2.5 text-sm border-l-2 transition-all duration-150 focus:outline-none"
        :class="[
            props.active
                ? 'border-[var(--colorPrincipal)] bg-gray-50 dark:bg-white/[0.06] text-gray-900 dark:text-white font-medium'
                : 'border-transparent text-gray-600 dark:text-gray-300 hover:border-[var(--colorPrincipal)] hover:bg-gray-50 dark:hover:bg-white/[0.04] hover:text-gray-900 dark:hover:text-white',
            props.disabled ? 'opacity-40 cursor-not-allowed pointer-events-none' : '',
        ]"
        :disabled="props.disabled"
    >
        <slot />
    </button>
</template>
