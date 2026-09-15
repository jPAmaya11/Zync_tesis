import { computed, ref } from 'vue';

export function useFileUploader(config = {}) {
    const {
        preset = 'pdf', // Preset a usar
        mode = 'single', // 'single' | 'multiple'
        accept = '.pdf', // Tipos de archivo aceptados
        maxSize = 5120, // Tamaño máximo en KB
        maxFiles = 10, // Máximo número de archivos
        showPreview = false, // Mostrar preview para imágenes
        allowedTypes = ['application/pdf'], // Tipos MIME permitidos
    } = config;

    const files = ref([]);
    const uploadMode = ref(mode);
    const isDragging = ref(false);

    // Configuraciones predefinidas
    const presets = {
        pdf: {
            accept: '.pdf',
            allowedTypes: ['application/pdf', 'application/x-pdf', 'binary/octet-stream'],
            maxSize: 5120,
            showPreview: false,
            icon: 'pdf',
        },
        images: {
            accept: 'image/*',
            allowedTypes: [
                'image/jpeg',
                'image/png',
                'image/jpg',
                'image/gif',
                'image/webp',
            ],
            maxSize: 2048,
            showPreview: true,
            icon: 'image',
        },
        documents: {
            accept: '.pdf,.doc,.docx',
            allowedTypes: [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            maxSize: 5120,
            showPreview: false,
            icon: 'document',
        },
        mixed: {
            accept: '.pdf,.doc,.docx,image/*',
            allowedTypes: [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
                'image/jpg',
                'image/gif',
            ],
            maxSize: 5120,
            showPreview: true,
            icon: 'mixed',
        },
    };

    const currentConfig = computed(() => {
        // Base: preset predefinido, o parámetros individuales si no existe.
        const base = presets[preset]
            ? presets[preset]
            : { accept, allowedTypes, maxSize, showPreview };
        // Override opcional de maxSize: si se pasa explícitamente, gana sobre el preset.
        // (retrocompatible: sin pasarlo, el comportamiento del preset no cambia)
        if (config.maxSize !== undefined && config.maxSize !== null) {
            return { ...base, maxSize: config.maxSize };
        }
        return base;
    });

    const validateFile = (file) => {
        // Validar tipo
        if (
            currentConfig.value.allowedTypes.length > 0 &&
            !currentConfig.value.allowedTypes.includes(file.type)
        ) {
            return {
                valid: false,
                error: `Tipo de archivo no permitido: ${file.type}`,
            };
        }

        // Validar tamaño
        const fileSizeKB = file.size / 1024;
        if (fileSizeKB > currentConfig.value.maxSize) {
            return {
                valid: false,
                error: `El archivo es muy grande: ${(fileSizeKB / 1024).toFixed(
                    2
                )}MB. Máximo: ${(currentConfig.value.maxSize / 1024).toFixed(
                    2
                )}MB`,
            };
        }

        return { valid: true };
    };

    const addFiles = (newFiles) => {
        console.log('Agregando archivos:', newFiles);
        const fileArray = Array.from(newFiles);

        if (uploadMode.value === 'single' && fileArray.length > 0) {
            // Modo single: reemplaza
            const file = fileArray[0];
            const validation = validateFile(file);

            if (!validation.valid) {
                console.warn('Validación fallida:', validation.error);
                if (window.showError) {
                    window.showError('Error de archivo', validation.error);
                }
                return;
            }

            files.value = [file];
        } else if (uploadMode.value === 'multiple') {
            // Modo múltiple: agrega
            const existingMap = new Map(files.value.map((f) => [f.name, f]));

            fileArray.forEach((file) => {
                if (files.value.length >= maxFiles) {
                    if (window.showWarning) {
                        window.showWarning(
                            'Límite alcanzado',
                            `Solo puedes subir máximo ${maxFiles} archivos.`
                        );
                    }
                    return;
                }

                if (existingMap.has(file.name)) {
                    if (window.showWarning) {
                        window.showWarning(
                            'Archivo duplicado',
                            `El archivo "${file.name}" ya ha sido seleccionado.`
                        );
                    }
                    return;
                }

                const validation = validateFile(file);
                if (!validation.valid) {
                    if (window.showError) {
                        window.showError('Error de archivo', validation.error);
                    }
                    return;
                }

                files.value.push(file);
                existingMap.set(file.name, file);
            });
        }
    };

    const removeFile = (index) => {
        files.value.splice(index, 1);
    };

    const clearFiles = () => {
        files.value = [];
    };

    const changeMode = (mode) => {
        uploadMode.value = mode;
        clearFiles();
    };

    const getPreviewUrl = (file) => {
        if (file.type.startsWith('image/')) {
            return URL.createObjectURL(file);
        }
        return null;
    };

    const getFileIcon = (file) => {
        if (file.type.startsWith('image/')) return 'image';
        if (file.type === 'application/pdf') return 'pdf';
        if (
            file.type === 'application/msword' ||
            file.type ===
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        )
            return 'word';
        return 'file';
    };

    const formatFileSize = (bytes) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (
            Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
        );
    };

    return {
        files,
        uploadMode,
        isDragging,
        currentConfig,
        addFiles,
        removeFile,
        clearFiles,
        changeMode,
        getPreviewUrl,
        getFileIcon,
        formatFileSize,
    };
}
