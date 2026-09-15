<script setup>
import { useFileUploader } from '@/Composables/useFileUploader';
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue';
import 'overlayscrollbars/overlayscrollbars.css';
import { ref, watch } from 'vue';

const props = defineProps({
    preset: {
        type: String,
        default: 'pdf', // 'pdf' | 'images' | 'documents' | 'mixed'
    },
    modelValue: {
        type: [Array, Object, File],
        default: null,
    },
    mode: {
        type: String,
        default: 'multiple', // 'single' | 'multiple'
    },
    // Override opcional del tamaño máximo en KB. Si se omite, manda el del preset.
    maxSize: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);

const uploader = useFileUploader({
    preset: props.preset,
    mode: props.mode,
    maxSize: props.maxSize ?? undefined,
});

// Sincronizar con v-model
watch(
    () => uploader.files.value,
    (newFiles) => {
        if (props.mode === 'single') {
            emit('update:modelValue', newFiles[0] || null);
        } else {
            emit('update:modelValue', newFiles);
        }
    },
    { deep: true }
);

watch(
    () => props.modelValue,
    (newValue) => {
        const normalized = Array.isArray(newValue)
            ? newValue
            : (newValue ? [newValue] : []);
        if (JSON.stringify(normalized) !== JSON.stringify(uploader.files.value)) {
            uploader.files.value = normalized;
        }
    }
);

const handleFileInput = (event) => {
    const files = event.target.files;
    if (files) {
        uploader.addFiles(files);
    }
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleDrop = (event) => {
    event.preventDefault();
    uploader.isDragging.value = false;
    const files = event.dataTransfer.files;
    if (files) {
        uploader.addFiles(files);
    }
};

const handleDragOver = (event) => {
    event.preventDefault();
    uploader.isDragging.value = true;
};

const handleDragLeave = () => {
    uploader.isDragging.value = false;
};
</script>

<template>
    <div class="file-uploader-container">
        <!-- Layout: Zona de carga a la izquierda + Listado a la derecha -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-72">
            <!-- Columna izquierda: Zona de carga -->
            <div class="lg:col-span-1 flex flex-col h-full">
                <!-- Selector de modo -->
                <div v-if="allowModeSwitch" class="mb-4 flex gap-2">
                    <button
                        @click="uploader.changeMode('single')"
                        :class="[
                            'flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2',
                            uploader.uploadMode.value === 'single'
                                ? 'bgPrincipal text-white shadow-md'
                                : 'bg-[var(--bg-secondary)] text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)]',
                        ]"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                            />
                        </svg>
                        Único
                    </button>
                    <button
                        @click="uploader.changeMode('multiple')"
                        :class="[
                            'flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2',
                            uploader.uploadMode.value === 'multiple'
                                ? 'bgPrincipal text-white shadow-md'
                                : 'bg-[var(--bg-secondary)] text-[var(--text-secondary)] hover:bg-[var(--bg-tertiary)]',
                        ]"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"
                            />
                        </svg>
                        Múlt.
                    </button>
                </div>

                <!-- Zona de carga drag & drop -->
                <div
                    class="relative group flex-1"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        :accept="uploader.currentConfig.value.accept"
                        :multiple="uploader.uploadMode.value === 'multiple'"
                        @change="handleFileInput"
                        @drop="handleDrop"
                        @dragover="handleDragOver"
                        @dragleave="handleDragLeave"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                    />
                    <div
                        :class="[
                            'relative h-full border-2 border-dashed rounded-lg p-4 text-center transition-all duration-300 cursor-pointer flex flex-col items-center justify-center',
                            uploader.isDragging.value
                                ? 'border-[var(--colorPrincipal)] bg-indigo-50 shadow-lg scale-[1.02]'
                                : 'border-[var(--border-color)] group-hover:border-[var(--colorPrincipal)] bg-gradient-to-br from-[var(--bg-tertiary)]/50 to-[var(--bg-secondary)] group-hover:shadow-lg',
                        ]"
                    >
                        <!-- Icono dinámico según tipo -->
                        <div class="flex justify-center mb-2">
                            <svg
                                v-if="preset === 'pdf'"
                                class="w-12 h-12 text-red-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <svg
                                v-else-if="preset === 'images'"
                                class="w-12 h-12 text-green-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                            <svg
                                v-else
                                class="w-12 h-12 text-indigo-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                />
                            </svg>
                        </div>

                        <p
                            class="text-xs font-bold text-[var(--text-primary)] mb-1"
                        >
                            Arrastra archivos o haz clic
                        </p>
                        <p class="text-xs text-[var(--text-tertiary)]">
                            {{ uploader.currentConfig.value.accept }} - Máx
                            {{
                                (
                                    uploader.currentConfig.value.maxSize / 1024
                                ).toFixed(0)
                            }}MB
                        </p>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Listado de archivos -->
            <div class="lg:col-span-2 flex flex-col h-full min-h-0 relative">
                <Transition name="fade-scale" mode="out-in">
                    <!-- Estado vacío -->
                    <div
                        v-if="uploader.files.value.length === 0"
                        key="empty"
                        class="flex-1 flex items-center justify-center border border-dashed border-[var(--border-color)] rounded-lg p-8 text-center"
                    >
                        <div>
                            <svg
                                class="w-12 h-12 mx-auto text-[var(--text-tertiary)] opacity-50 mb-3"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <p
                                class="text-sm font-semibold text-[var(--text-secondary)] mb-1"
                            >
                                No hay archivos seleccionados
                            </p>
                            <p class="text-xs text-[var(--text-tertiary)]">
                                Los archivos aparecerán aquí
                            </p>
                        </div>
                    </div>

                    <!-- Lista de archivos -->
                    <div v-else key="list" class="flex flex-col h-full min-h-0">
                        <div
                            class="flex-shrink-0 flex items-center justify-between pb-3 border-b border-[var(--border-color)]"
                        >
                            <span
                                class="text-sm font-bold text-[var(--text-primary)]"
                            >
                                {{ uploader.files.value.length }} archivo{{
                                    uploader.files.value.length !== 1 ? 's' : ''
                                }}
                            </span>
                            <button
                                @click="uploader.clearFiles()"
                                class="text-xs text-[var(--colorSecundario)] hover:text-red-700 font-semibold transition-colors"
                            >
                                Limpiar todo
                            </button>
                        </div>

                        <!-- Scroll container -->
                        <OverlayScrollbarsComponent
                            :options="{
                                scrollbars: {
                                    autoHide: 'leave',
                                    autoHideDelay: 300,
                                },
                                overflow: { x: 'hidden' },
                            }"
                            class="os-host os-theme-light dark:os-theme-dark flex-1 min-h-0 mt-3"
                        >
                            <TransitionGroup
                                name="doc-list"
                                tag="div"
                                class="space-y-2 pr-2"
                            >
                                <div
                                    v-for="(file, index) in uploader.files.value"
                                    :key="file.name + index"
                                    class="group relative border border-[var(--border-color)] rounded-lg p-3 bg-[var(--bg-secondary)] hover:shadow-md transition-all duration-200"
                                >
                                    <div class="flex items-start gap-3">
                                        <!-- Preview o icono -->
                                        <div class="flex-shrink-0">
                                            <!-- Preview para imágenes -->
                                            <div
                                                v-if="
                                                    uploader.currentConfig.value
                                                        .showPreview &&
                                                    uploader.getPreviewUrl(file)
                                                "
                                                class="w-16 h-16 rounded-lg overflow-hidden border border-[var(--border-color)]"
                                            >
                                                <img
                                                    :src="
                                                        uploader.getPreviewUrl(
                                                            file
                                                        )
                                                    "
                                                    :alt="file.name"
                                                    class="w-full h-full object-cover"
                                                />
                                            </div>
                                            <!-- Icono para documentos -->
                                            <div
                                                v-else
                                                class="w-12 h-12 flex items-center justify-center rounded-lg bg-[var(--bg-tertiary)]"
                                            >
                                                <!-- PDF -->
                                                <svg
                                                    v-if="
                                                        uploader.getFileIcon(
                                                            file
                                                        ) === 'pdf'
                                                    "
                                                    class="w-6 h-6 text-red-500"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                                <!-- Word -->
                                                <svg
                                                    v-else-if="
                                                        uploader.getFileIcon(
                                                            file
                                                        ) === 'word'
                                                    "
                                                    class="w-6 h-6 text-blue-500"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path
                                                        d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2h-1.528A6 6 0 004 9.528V4z"
                                                    />
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M8 10a4 4 0 00-3.446 6.032l-1.261 1.26a1 1 0 101.414 1.415l1.261-1.261A4 4 0 108 10zm-2 4a2 2 0 114 0 2 2 0 01-4 0z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                                <!-- Genérico -->
                                                <svg
                                                    v-else
                                                    class="w-6 h-6 text-gray-500"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                                    />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- Info del archivo -->
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm font-semibold text-[var(--text-primary)] truncate"
                                            >
                                                {{ file.name }}
                                            </p>
                                            <p
                                                class="text-xs text-[var(--text-tertiary)] mt-1"
                                            >
                                                {{
                                                    uploader.formatFileSize(
                                                        file.size
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <!-- Botón eliminar -->
                                        <button
                                            @click="uploader.removeFile(index)"
                                            class="flex-shrink-0 p-1.5 text-[var(--colorSecundario)] hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                                            title="Eliminar"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </TransitionGroup>
                        </OverlayScrollbarsComponent>
                    </div>
                </Transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
.doc-list-enter-active {
    animation: slideInUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.doc-list-leave-active {
    animation: slideOutRight 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: absolute;
    width: calc(100% - 0.5rem);
}

.doc-list-move {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-scale-enter-active,
.fade-scale-leave-active {
    transition: opacity 0.3s ease;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
}

@keyframes slideInUp {
    0% {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slideOutRight {
    0% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateX(30px) scale(0.9);
    }
}
</style>
