<template>
    <!-- Backdrop del modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 dark:bg-slate-950/75"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    @click.self="closeOnOverlayClick && closeModal()"
                    class="flex min-h-full items-center justify-center px-4 py-8 cursor-pointer"
                >
                <div
                    class="relative cursor-default"
                    :class="[sizeClasses, show ? 'animate-modal-slide-up' : '']"
                >
                    <!-- Main modal container -->
                    <!-- Las clases `modal-view__*` son ganchos SIN estilos propios: existen
                         para que un módulo pueda re-vestir el modal desde su hoja de estilos
                         usando `panelClass`. No cambian nada por sí solas. -->
                    <div
                        class="modal-view__panel relative bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                        :class="panelClass"
                    >
                        <!-- Header with gradient -->
                        <div
                            v-if="!hideHeader"
                            class="modal-view__head relative bg-gradient-to-r from-indigo-600 to-cyan-600 dark:from-indigo-500 dark:to-cyan-500 px-8 py-6"
                        >
                            <div
                                class="relative flex items-center justify-between"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Icon with animated border -->
                                    <div v-if="icon" class="relative">
                                        <div
                                            class="absolute inset-0 bg-white/20 rounded-xl blur-md"
                                        ></div>
                                        <div
                                            :class="[
                                                'relative bg-white/10 backdrop-blur-sm p-3 rounded-xl border border-white/20',
                                            ]"
                                        >
                                            <component
                                                :is="icon"
                                                class="w-7 h-7 text-white drop-shadow-lg"
                                            />
                                        </div>
                                    </div>

                                    <!-- Title + Subtitle -->
                                    <div>
                                        <h2
                                            class="modal-view__titulo text-2xl font-bold text-white drop-shadow-md"
                                            id="modal-title"
                                        >
                                            {{ title }}
                                        </h2>
                                        <!-- Slot subtitle: para info adicional (key, status, etc.) -->
                                        <div
                                            v-if="$slots.subtitle || subtitle"
                                            class="modal-view__sub text-xs text-white/80 mt-1"
                                        >
                                            <slot name="subtitle">{{ subtitle }}</slot>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right side: acciones del header + close -->
                                <div class="flex items-center gap-2">
                                    <!-- Slot header-actions: para botones como "+Nuevo", "Editar", etc. -->
                                    <slot name="header-actions" />

                                    <!-- Close button -->
                                    <button
                                        @click="closeModal"
                                        class="modal-view__cerrar relative group bg-white/10 hover:bg-white/20 backdrop-blur-sm p-2.5 rounded-xl border border-white/20 transition-all duration-200"
                                    >
                                        <svg
                                            class="w-5 h-5 text-white transition-transform group-hover:rotate-90 duration-300"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Contenido del modal -->
                        <div :class="contentClasses">
                            <slot />
                        </div>

                        <!-- Footer del modal -->
                        <div
                            v-if="!hideFooter && hasFooterContent"
                            class="flex items-center justify-end px-8 py-6 gap-3 border-t border-gray-200/70 dark:border-gray-700/70"
                        >
                            <slot name="footer">
                                <!-- Botones por defecto -->
                                <button
                                    v-if="showCancelButton"
                                    @click="closeModal"
                                    type="button"
                                    class="group relative px-6 py-2.5 rounded-xl font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-300/50 dark:border-gray-600/50 transition-all duration-200 shadow-sm hover:shadow-md"
                                >
                                    <span
                                        class="relative z-10 flex items-center gap-2"
                                    >
                                        <svg
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                        {{ cancelText }}
                                    </span>
                                </button>

                                <button
                                    v-if="showConfirmButton"
                                    @click="confirmAction"
                                    type="button"
                                    :class="[
                                        'relative px-8 py-2.5 rounded-xl font-bold text-white shadow-lg hover:shadow-xl border transition-colors duration-200',
                                        confirmButtonClasses,
                                    ]"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="w-5 h-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            stroke-width="2.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        {{ confirmText }}
                                    </span>
                                </button>
                            </slot>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, useSlots } from 'vue';

// Props
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) =>
            [
                'xs',
                'sm',
                'md',
                'lg',
                'xl',
                '2xl',
                '3xl',
                '4xl',
                'full',
            ].includes(value),
    },
    // Clase extra para el panel (opcional, aditiva): permite a un módulo darle
    // su propia piel —radios, cabecera— sin tocar este componente compartido.
    // Por defecto vacía, así que ninguna vista existente cambia.
    panelClass: {
        type: String,
        default: '',
    },
    icon: {
        type: [String, Object],
        default: null,
    },
    iconType: {
        type: String,
        default: 'info',
        validator: (value) =>
            ['info', 'success', 'warning', 'error'].includes(value),
    },
    hideHeader: {
        type: Boolean,
        default: false,
    },
    hideFooter: {
        type: Boolean,
        default: false,
    },
    showCancelButton: {
        type: Boolean,
        default: false,
    },
    showConfirmButton: {
        type: Boolean,
        default: false,
    },
    cancelText: {
        type: String,
        default: 'Cancelar',
    },
    confirmText: {
        type: String,
        default: 'Confirmar',
    },
    confirmType: {
        type: String,
        default: 'primary',
        validator: (value) =>
            ['primary', 'success', 'warning', 'danger'].includes(value),
    },
    closeOnOverlayClick: {
        type: Boolean,
        default: true,
    },
    persistent: {
        type: Boolean,
        default: false,
    },
    minHeight: {
        type: String,
        default: '',
    },
});

// Emits
const emit = defineEmits(['close', 'confirm']);

// Slots
const slots = useSlots();

// Computed
const sizeClasses = computed(() => {
    const sizes = {
        xs: 'w-full max-w-xs',
        sm: 'w-full max-w-sm',
        md: 'w-full max-w-md',
        lg: 'w-full max-w-lg',
        xl: 'w-full max-w-xl',
        '2xl': 'w-full max-w-2xl',
        '3xl': 'w-full max-w-3xl',
        '4xl': 'w-full max-w-4xl',
        full: 'w-full h-full',
    };
    return sizes[props.size] || sizes.md;
});

const contentClasses = computed(() => {
    let classes = 'px-8 py-4';

    if (props.size === 'full') {
        classes += ' overflow-y-auto max-h-full';
    } else if (props.minHeight) {
        // Si hay minHeight personalizado, aplicarlo
        classes += ` overflow-y-auto ${props.minHeight}`;
    } else {
        classes += ' max-h-[70vh] overflow-y-auto';
    }

    return classes;
});

const confirmButtonClasses = computed(() => {
    const classes = {
        primary: 'bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 border-indigo-700/20 dark:border-indigo-400/20',
        success: 'bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 border-emerald-700/20 dark:border-emerald-400/20',
        warning: 'bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600 border-amber-700/20 dark:border-amber-400/20',
        danger:  'bg-rose-600 hover:bg-rose-700 dark:bg-rose-500 dark:hover:bg-rose-600 border-rose-700/20 dark:border-rose-400/20',
    };
    return classes[props.confirmType] || classes.primary;
});

const hasFooterContent = computed(() => {
    return slots.footer || props.showCancelButton || props.showConfirmButton;
});

// Methods
const closeModal = () => {
    emit('close');
};

const confirmAction = () => {
    emit('confirm');
};

// Manejo de tecla Escape
const handleEscape = (event) => {
    if (event.key === 'Escape' && props.show && !props.persistent) {
        closeModal();
    }
};

// Lifecycle
import { onMounted, onUnmounted } from 'vue';

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape);
});
</script>

<style scoped>
@keyframes modal-slide-up {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.animate-modal-slide-up {
    animation: modal-slide-up 0.2s ease-out;
}
</style>
