import { ref } from 'vue';

/**
 * Hook para manejar drag & drop del ícono del proyecto.
 *
 * Acepta emojis (texto corto), archivos de imagen (PNG/JPG/SVG) o URLs de imagen
 * arrastradas desde el navegador. Convierte imágenes a dataURL.
 *
 * Uso:
 *   const { iconValue, previewUrl, dragOver, handlers, isIconUrl, clear } = useProjectIconDrop(initialIcon);
 *
 *   <div v-bind="handlers" :class="dragOver ? '...' : '...'">
 *
 * El padre lee iconValue.value para enviarlo al servidor.
 */
// Tope del ícono: el dataURL base64 se guarda en BD (mediumtext). 200 KB de imagen
// ≈ 273 KB de base64, dentro del límite. Más grande → se rechaza con alerta.
const MAX_ICON_BYTES = 200 * 1024;

export function useProjectIconDrop(initialIcon = '') {
    const iconValue = ref(initialIcon);
    const previewUrl = ref(isIconUrl(initialIcon) ? initialIcon : null);
    const dragOver = ref(false);

    // Valida tamaño y, si pasa, convierte la imagen a dataURL. Si excede, avisa y no la carga.
    function processImageFile(file) {
        if (!file || !file.type.startsWith('image/')) return;
        if (file.size > MAX_ICON_BYTES) {
            window.showToast?.(
                `La imagen es muy grande (${Math.round(file.size / 1024)} KB). Máximo 200 KB.`,
                'error',
                { timer: 3500 }
            );
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            previewUrl.value = ev.target.result;
            iconValue.value = ev.target.result;
        };
        reader.readAsDataURL(file);
    }

    function isIconUrl(icon) {
        if (!icon) return false;
        return icon.startsWith('http') || icon.startsWith('data:image');
    }

    function onDragOver(e) {
        e.preventDefault();
        dragOver.value = true;
    }

    function onDragLeave() {
        dragOver.value = false;
    }

    function onDrop(e) {
        e.preventDefault();
        dragOver.value = false;

        // 1. Emoji o texto corto pegado/arrastrado
        const text = e.dataTransfer.getData('text/plain');
        if (text && text.trim().length <= 10) {
            iconValue.value = text.trim();
            previewUrl.value = null;
            return;
        }

        // 2. Archivo de imagen — valida tamaño y convierte a dataURL
        const file = e.dataTransfer.files?.[0];
        if (file && file.type.startsWith('image/')) {
            processImageFile(file);
            return;
        }

        // 3. URL de imagen arrastrada (ej: desde otra pestaña del navegador)
        const uri =
            e.dataTransfer.getData('text/uri-list') ||
            e.dataTransfer.getData('text/html');
        const urlMatch = uri?.match(
            /https?:\/\/[^\s"'<>]+\.(?:png|jpg|jpeg|gif|svg|webp)/i
        );
        if (urlMatch) {
            previewUrl.value = urlMatch[0];
            iconValue.value = urlMatch[0];
        }
    }

    function handleFile(file) {
        processImageFile(file);
    }

    function clear() {
        iconValue.value = '';
        previewUrl.value = null;
    }

    /** Setea un valor inicial (útil al abrir modal de edición). */
    function setIcon(icon) {
        iconValue.value = icon || '';
        previewUrl.value = isIconUrl(icon) ? icon : null;
    }

    return {
        iconValue,
        previewUrl,
        dragOver,
        handlers: {
            dragover: onDragOver,
            dragleave: onDragLeave,
            drop: onDrop,
        },
        isIconUrl,
        clear,
        setIcon,
        handleFile,
    };
}
