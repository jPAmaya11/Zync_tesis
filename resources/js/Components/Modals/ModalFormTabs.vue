<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show"
                @click.self="$emit('close')"
                class="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-gradient-to-br from-slate-900/60 via-indigo-900/40 to-slate-900/60 dark:from-slate-950/80 dark:via-indigo-950/60 dark:to-slate-950/80 cursor-pointer px-4 py-8"
            >
                <div
                    class="relative w-full max-w-3xl cursor-default modal-content"
                    @click.stop
                >
                    <!-- Glow effect background -->
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-blue-500/20 via-indigo-500/20 to-cyan-500/20 dark:from-blue-600/10 dark:via-indigo-600/10 dark:to-cyan-600/10 rounded-2xl blur-2xl -z-10"
                    ></div>

                    <!-- Main modal container -->
                    <div
                        class="relative bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden flex flex-col"
                        style="height: 800px; max-height: 100vh"
                    >
                        <!-- Header with gradient -->
                        <div
                            class="relative bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-600 dark:from-blue-500 dark:via-indigo-500 dark:to-cyan-500 px-8 py-6 flex-shrink-0"
                        >
                            <!-- Decorative pattern overlay -->
                            <div
                                class="absolute inset-0 bg-grid-white/10 [mask-image:radial-gradient(white,transparent_85%)]"
                            ></div>

                            <div
                                class="relative flex items-center justify-between"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Icon -->
                                    <div class="relative">
                                        <div
                                            class="absolute inset-0 bg-white/20 rounded-xl blur-md"
                                        ></div>
                                        <div
                                            class="relative bg-white/10 backdrop-blur-sm p-3 rounded-xl border border-white/20"
                                        >
                                            <svg
                                                class="w-7 h-7 text-white drop-shadow-lg"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.5"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Title -->
                                    <div>
                                        <h2
                                            class="text-2xl font-bold text-white drop-shadow-md"
                                        >
                                            {{ title }}
                                        </h2>
                                        <p
                                            class="text-blue-100/90 text-sm mt-1"
                                        >
                                            Navegue por las pestañas para
                                            completar
                                        </p>
                                    </div>
                                </div>

                                <!-- Close button -->
                                <button
                                    @click="$emit('close')"
                                    :disabled="loading"
                                    class="relative group bg-white/10 hover:bg-white/20 backdrop-blur-sm p-2.5 rounded-xl border border-white/20 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
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

                            <!-- Modern Tabs -->
                            <div v-if="tabs?.length" class="relative mt-6">
                                <div class="flex gap-2">
                                    <button
                                        v-for="tab in tabs"
                                        :key="tab.value"
                                        type="button"
                                        @click="
                                            $emit('update:tabActiva', tab.value)
                                        "
                                        class="relative flex-1 px-4 py-3 rounded-xl font-semibold text-sm transition-all duration-200 flex items-center justify-center gap-2 overflow-hidden"
                                        :class="
                                            tabActiva === tab.value
                                                ? 'bg-white/20 backdrop-blur-sm text-white border border-white/30 shadow-lg'
                                                : 'bg-white/5 backdrop-blur-sm text-blue-100/70 border border-white/10 hover:bg-white/10 hover:text-white hover:border-white/20'
                                        "
                                    >
                                        <component
                                            v-if="tab.icon"
                                            :is="tab.icon"
                                            class="w-4 h-4"
                                        />
                                        <span>{{ tab.label }}</span>

                                        <!-- Active dot indicator -->
                                        <div
                                            v-if="tabActiva === tab.value"
                                            class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-white rounded-full shadow-lg"
                                        ></div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Content area with form -->
                        <form
                            @submit.prevent="handleSubmit"
                            class="flex-1 flex flex-col min-h-0"
                            autocomplete="off"
                        >
                            <!-- Scrollable content - ALTURA FIJA -->
                            <div class="flex-1 min-h-0 overflow-hidden">
                                <OverlayScrollbarsComponent
                                    element="div"
                                    :options="scrollbarOptions"
                                    defer
                                    class="h-full"
                                >
                                    <div class="px-8 py-6">
                                        <Transition
                                            :name="slideDirection"
                                            mode="out-in"
                                        >
                                            <div
                                                :key="tabActiva"
                                                class="space-y-5"
                                            >
                                                <slot
                                                    :form="form"
                                                    :errors="errors"
                                                />
                                            </div>
                                        </Transition>
                                    </div>
                                </OverlayScrollbarsComponent>
                            </div>

                            <!-- Action buttons - SIEMPRE VISIBLE -->
                            <div
                                class="px-8 py-6 border-t border-gray-200/70 dark:border-gray-700/70 bg-white/50 dark:bg-gray-900/50 flex-shrink-0"
                            >
                                <div
                                    class="flex items-center justify-end gap-3"
                                >
                                    <button
                                        type="button"
                                        @click="$emit('close')"
                                        :disabled="loading"
                                        class="group relative px-6 py-2.5 rounded-xl font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-300/50 dark:border-gray-600/50 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md"
                                    >
                                        <span class="flex items-center gap-2">
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
                                            Cancelar
                                        </span>
                                    </button>

                                    <button
                                        type="submit"
                                        :disabled="loading"
                                        class="group relative px-8 py-2.5 rounded-xl font-bold text-white bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-600 hover:from-blue-700 hover:via-indigo-700 hover:to-purple-700 shadow-lg hover:shadow-xl hover:shadow-indigo-500/30 border border-blue-700/20 transition-all duration-300 disabled:opacity-70 disabled:cursor-not-allowed overflow-hidden"
                                    >
                                        <!-- Shine effect -->
                                        <div
                                            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent translate-x-[-200%] group-hover:translate-x-[200%] transition-transform duration-700"
                                        ></div>

                                        <span
                                            class="relative flex items-center justify-center gap-2"
                                        >
                                            <template v-if="loading">
                                                <svg
                                                    class="w-5 h-5 animate-spin"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                >
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                    ></circle>
                                                    <path
                                                        class="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                    ></path>
                                                </svg>
                                                <span>Procesando...</span>
                                            </template>
                                            <template v-else>
                                                <svg
                                                    class="w-5 h-5 transition-transform group-hover:scale-110"
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
                                                <span>{{ submitLabel }}</span>
                                            </template>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue';
import 'overlayscrollbars/overlayscrollbars.css';
import { ref, watch } from 'vue';

const loading = ref(false);
const errors = ref({});
const slideDirection = ref('slide-right');

const props = defineProps({
    show: Boolean,
    title: String,
    submitLabel: String,
    form: Object,
    endpoint: String,
    method: String,
    transform: {
        type: Function,
        default: (form) => form,
    },
    tabActiva: String,
    tabs: {
        type: Array,
        default: null,
    },
});

const emit = defineEmits([
    'close',
    'success',
    'update:tabActiva',
    'submit',
    'general-error',
]);

const scrollbarOptions = {
    scrollbars: {
        theme: 'os-theme-dark',
        visibility: 'auto',
        autoHide: 'leave',
        autoHideDelay: 800,
    },
    overflow: {
        x: 'hidden',
        y: 'scroll',
    },
};

// Detectar dirección cuando cambie de tab
watch(
    () => props.tabActiva,
    (newTab, oldTab) => {
        if (props.tabs && newTab && oldTab) {
            const newIndex = props.tabs.findIndex((t) => t.value === newTab);
            const oldIndex = props.tabs.findIndex((t) => t.value === oldTab);
            slideDirection.value =
                newIndex > oldIndex ? 'slide-left' : 'slide-right';
        }
    }
);

function handleSubmit() {
    errors.value = {};
    loading.value = true;

    const data = props.transform(props.form);
    emit('submit', data);

    router[props.method](props.endpoint, data, {
        onSuccess: (page) => {
            const successMessage =
                page.props?.success ||
                (page.props?.alert?.type === 'success'
                    ? page.props.alert.message
                    : null);

            if (successMessage) {
                emit('success', successMessage);
            }
            loading.value = false;
        },
        onError: (err) => {
            errors.value = err;
            loading.value = false;

            if (err?.general) {
                emit('general-error', err.general);
            } else if (!err || Object.keys(err).length === 0) {
                emit('general-error', 'Error de conexión. Inténtalo de nuevo.');
            }
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}
</script>

<style scoped>
/* Grid pattern for header */
.bg-grid-white {
    background-image: linear-gradient(
            rgba(255, 255, 255, 0.05) 1px,
            transparent 1px
        ),
        linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
    background-size: 20px 20px;
}

/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .modal-content {
    animation: modalIn 0.3s ease-out;
}

.modal-leave-active .modal-content {
    animation: modalOut 0.2s ease-in;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes modalOut {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateY(-10px) scale(0.98);
    }
}

/* Tab slide animations - Left (next) */
.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.25s ease-out;
}

.slide-left-enter-from {
    opacity: 0;
    transform: translateX(30px);
}

.slide-left-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}

/* Tab slide animations - Right (prev) */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.25s ease-out;
}

.slide-right-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}

.slide-right-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

/* OverlayScrollbars custom theme */
:deep(.os-scrollbar) {
    --os-size: 10px;
    --os-padding-perpendicular: 2px;
    --os-padding-axis: 2px;
    --os-track-border-radius: 5px;
    --os-track-bg: rgba(0, 0, 0, 0.05);
    --os-track-bg-hover: rgba(0, 0, 0, 0.08);
    --os-track-bg-active: rgba(0, 0, 0, 0.1);
    --os-handle-border-radius: 5px;
    --os-handle-bg: rgba(0, 0, 0, 0.2);
    --os-handle-bg-hover: rgba(0, 0, 0, 0.35);
    --os-handle-bg-active: rgba(0, 0, 0, 0.5);
    --os-handle-min-size: 30px;
}

.dark :deep(.os-scrollbar) {
    --os-track-bg: rgba(255, 255, 255, 0.05);
    --os-track-bg-hover: rgba(255, 255, 255, 0.08);
    --os-track-bg-active: rgba(255, 255, 255, 0.1);
    --os-handle-bg: rgba(255, 255, 255, 0.2);
    --os-handle-bg-hover: rgba(255, 255, 255, 0.35);
    --os-handle-bg-active: rgba(255, 255, 255, 0.5);
}
</style>
