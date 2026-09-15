<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        @click="!disabled && $emit('update:modelValue', !modelValue)"
        class="switch group"
        :class="[
            sizeClasses.track,
            modelValue ? 'switch--active' : 'switch--inactive',
            { 'switch--disabled': disabled },
        ]"
        :disabled="disabled"
    >
        <!-- Track background con efecto -->
        <span class="switch-bg" />

        <!-- Thumb -->
        <span
            class="switch-thumb"
            :class="[
                sizeClasses.thumb,
                modelValue ? sizeClasses.translate : 'translate-x-0',
            ]"
        >
            <!-- Icono check (opcional, visible cuando activo) -->
            <svg
                v-if="modelValue"
                class="switch-icon switch-icon--check"
                :class="sizeClasses.icon"
                viewBox="0 0 12 12"
                fill="none"
            >
                <path
                    d="M3.5 6L5.5 8L8.5 4"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
            <!-- Icono X (opcional, visible cuando inactivo) -->
            <svg
                v-else
                class="switch-icon switch-icon--x"
                :class="sizeClasses.icon"
                viewBox="0 0 12 12"
                fill="none"
            >
                <path
                    d="M4 4L8 8M8 4L4 8"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                />
            </svg>
        </span>
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);

const sizeClasses = computed(() => {
    const sizes = {
        sm: {
            track: 'switch--sm',
            thumb: 'w-3.5 h-3.5',
            translate: 'translate-x-[14px]',
            icon: 'w-2 h-2',
        },
        md: {
            track: 'switch--md',
            thumb: 'w-[18px] h-[18px]',
            translate: 'translate-x-[18px]',
            icon: 'w-2.5 h-2.5',
        },
        lg: {
            track: 'switch--lg',
            thumb: 'w-[22px] h-[22px]',
            translate: 'translate-x-[22px]',
            icon: 'w-3 h-3',
        },
    };
    return sizes[props.size];
});
</script>

<style scoped>
.switch {
    position: relative;
    border-radius: 9999px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

/* Tamaños */
.switch--sm {
    width: 32px;
    height: 18px;
}

.switch--md {
    width: 40px;
    height: 22px;
}

.switch--lg {
    width: 48px;
    height: 26px;
}

/* Background del track */
.switch-bg {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    transition: all 0.25s ease;
}

/* Estado inactivo - Light */
.switch--inactive .switch-bg {
    background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1),
        0 1px 0 rgba(255, 255, 255, 0.5);
}

/* Estado inactivo - Dark */
:root.dark .switch--inactive .switch-bg,
.dark .switch--inactive .switch-bg {
    background: linear-gradient(180deg, #374151 0%, #1f2937 100%);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.3),
        0 1px 0 rgba(255, 255, 255, 0.05);
}

/* Estado activo - Light */
.switch--active .switch-bg {
    background: linear-gradient(180deg, #079DED 0%, #067DBE 100%);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1),
        0 0 12px rgba(6, 125, 190, 0.4), 0 1px 0 rgba(255, 255, 255, 0.1);
}

/* Estado activo - Dark */
:root.dark .switch--active .switch-bg,
.dark .switch--active .switch-bg {
    background: linear-gradient(180deg, #079DED 0%, #067DBE 100%);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.2),
        0 0 16px rgba(7, 157, 237, 0.35), 0 1px 0 rgba(255, 255, 255, 0.05);
}

/* Hover effects */
.switch:not(.switch--disabled):hover .switch-bg {
    filter: brightness(1.05);
}

.switch--active:not(.switch--disabled):hover .switch-bg {
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1),
        0 0 20px rgba(6, 125, 190, 0.5), 0 1px 0 rgba(255, 255, 255, 0.1);
}

/* Thumb */
.switch-thumb {
    position: absolute;
    top: 50%;
    left: 2px;
    transform: translateY(-50%);
    border-radius: 9999px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15), 0 2px 6px rgba(0, 0, 0, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 1);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

/* Thumb en dark mode */
:root.dark .switch-thumb,
.dark .switch-thumb {
    background: linear-gradient(180deg, #ffffff 0%, #e2e8f0 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 2px 6px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

/* Hover en thumb */
.switch:not(.switch--disabled):hover .switch-thumb {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

/* Active press effect */
.switch:not(.switch--disabled):active .switch-thumb {
    transform: translateY(-50%) scale(0.95);
}

.switch-thumb.translate-x-0 {
    transform: translateY(-50%) translateX(0);
}

.switch-thumb.translate-x-\[14px\] {
    transform: translateY(-50%) translateX(14px);
}

.switch-thumb.translate-x-\[18px\] {
    transform: translateY(-50%) translateX(18px);
}

.switch-thumb.translate-x-\[22px\] {
    transform: translateY(-50%) translateX(22px);
}

/* Iconos dentro del thumb */
.switch-icon {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.switch-icon--check {
    color: #067DBE;
    opacity: 1;
    transform: scale(1);
}

.switch-icon--x {
    color: #94a3b8;
    opacity: 0.7;
    transform: scale(0.9);
}

:root.dark .switch-icon--x,
.dark .switch-icon--x {
    color: #64748b;
}

/* Focus visible */
.switch:focus-visible {
    outline: none;
}

.switch:focus-visible .switch-bg {
    box-shadow: 0 0 0 3px rgba(6, 125, 190, 0.3),
        inset 0 1px 3px rgba(0, 0, 0, 0.1);
}

.switch--active:focus-visible .switch-bg {
    box-shadow: 0 0 0 3px rgba(6, 125, 190, 0.3),
        0 0 12px rgba(6, 125, 190, 0.4), inset 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Disabled */
.switch--disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

/* Reducir movimiento */
@media (prefers-reduced-motion: reduce) {
    .switch-thumb,
    .switch-bg,
    .switch-icon {
        transition: none;
    }
}
</style>
