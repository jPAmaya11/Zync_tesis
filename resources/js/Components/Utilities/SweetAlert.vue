<template>
    <div></div>
</template>

<script setup>
import { watch, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const page = usePage();

// Detectar si está en modo dark
const isDarkMode = computed(() => {
    return document.documentElement.classList.contains("dark");
});

// Configuraciones por defecto para cada tipo (modo claro)
const alertTypes = {
    success: {
        icon: "success",
        confirmButtonColor: "#10b981",
        iconColor: "#10b981",
        background: "#f0fdf4",
        color: "#065f46",
    },
    error: {
        icon: "error",
        confirmButtonColor: "#ef4444",
        iconColor: "#ef4444",
        background: "#fef2f2",
        color: "#991b1b",
    },
    warning: {
        icon: "warning",
        confirmButtonColor: "#f59e0b",
        iconColor: "#f59e0b",
        background: "#fffbeb",
        color: "#92400e",
    },
    info: {
        icon: "info",
        confirmButtonColor: "#3b82f6",
        iconColor: "#3b82f6",
        background: "#eff6ff",
        color: "#1e40af",
    },
    question: {
        icon: "question",
        confirmButtonColor: "#8b5cf6",
        iconColor: "#8b5cf6",
        background: "#faf5ff",
        color: "#6b21a8",
    },
};

// Configuraciones para modo dark
const alertTypesDark = {
    success: {
        icon: "success",
        confirmButtonColor: "#10b981",
        iconColor: "#34d399",
        background: "#064e3b",
        color: "#d1fae5",
    },
    error: {
        icon: "error",
        confirmButtonColor: "#ef4444",
        iconColor: "#f87171",
        background: "#7f1d1d",
        color: "#fecaca",
    },
    warning: {
        icon: "warning",
        confirmButtonColor: "#f59e0b",
        iconColor: "#fbbf24",
        background: "#78350f",
        color: "#fef3c7",
    },
    info: {
        icon: "info",
        confirmButtonColor: "#3b82f6",
        iconColor: "#60a5fa",
        background: "#1e3a8a",
        color: "#dbeafe",
    },
    question: {
        icon: "question",
        confirmButtonColor: "#8b5cf6",
        iconColor: "#a78bfa",
        background: "#581c87",
        color: "#f3e8ff",
    },
};

// Configuraciones especiales para diferentes modalidades
const alertModes = {
    toast: {
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true,
        width: "420px",
        padding: "16px",
        customClass: {
            popup: "swal2-toast-custom",
            title: "swal2-toast-title",
            htmlContainer: "swal2-toast-content",
            timerProgressBar: "swal2-toast-progress",
        },
        didOpen: (toast) => {
            toast.addEventListener("mouseenter", Swal.stopTimer);
            toast.addEventListener("mouseleave", Swal.resumeTimer);
        },
    },
    confirm: {
        showCancelButton: true,
        cancelButtonText: "Cancelar",
        cancelButtonColor: "#6b7280",
        confirmButtonText: "Confirmar",
        reverseButtons: true,
    },
    input: {
        input: "text",
        showCancelButton: true,
        cancelButtonText: "Cancelar",
        confirmButtonText: "Aceptar",
        inputValidator: (value) => {
            if (!value) return "Debes ingresar un valor";
        },
    },
    loading: {
        title: "Cargando...",
        html: "Por favor espera un momento",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        },
    },
};

const showAlert = (alertData) => {
    if (!alertData) return;

    // Configuración base
    const config = {
        text: alertData.message || alertData.text || "",
        html: alertData.html || null,
        icon: alertData.icon || alertData.type || "info",
        confirmButtonText: alertData.confirmButtonText || "Entendido",
        customClass: {
            popup: isDarkMode.value
                ? "swal2-smooth-show swal2-dark"
                : "swal2-smooth-show",
            title: "text-lg font-semibold",
            htmlContainer: "text-sm text-gray-600",
        },
    };

    // Solo asignar título si viene uno específico
    if (alertData.title) {
        config.title = alertData.title;
    }

    // Solo aplicar allowOutsideClick y allowEscapeKey si NO es toast
    if (alertData.mode !== "toast") {
        config.allowOutsideClick = alertData.allowOutsideClick !== false;
        config.allowEscapeKey = alertData.allowEscapeKey !== false;
    }

    // Aplicar configuración del tipo según el modo (dark o light)
    const typeConfig = isDarkMode.value
        ? alertTypesDark[alertData.type] || alertTypesDark.info
        : alertTypes[alertData.type] || alertTypes.info;
    Object.assign(config, typeConfig);

    // Aplicar configuración del modo
    if (alertData.mode) {
        const modeConfig = alertModes[alertData.mode];
        if (modeConfig) {
            Object.assign(config, modeConfig);

            // Para toasts, eliminar parámetros incompatibles si se añadieron por error
            if (alertData.mode === "toast") {
                delete config.allowOutsideClick;
                delete config.allowEscapeKey;
                delete config.confirmButtonText;
            }
        }
    }

    // Configuraciones especiales
    if (alertData.timer) {
        config.timer = alertData.timer;
        config.timerProgressBar = true;
    }

    if (alertData.width) {
        config.width = alertData.width;
    }

    if (alertData.padding) {
        config.padding = alertData.padding;
    }

    if (alertData.showCloseButton) {
        config.showCloseButton = true;
    }

    if (alertData.backdrop !== undefined) {
        config.backdrop = alertData.backdrop;
    }

    // Botones personalizados
    if (alertData.confirmButtonText) {
        config.confirmButtonText = alertData.confirmButtonText;
    }

    if (alertData.cancelButtonText) {
        config.cancelButtonText = alertData.cancelButtonText;
        config.showCancelButton = true;
    }

    if (alertData.denyButtonText) {
        config.denyButtonText = alertData.denyButtonText;
        config.showDenyButton = true;
    }

    // Input personalizado
    if (alertData.input) {
        config.input = alertData.input;
        config.inputPlaceholder = alertData.inputPlaceholder || "";
        config.inputValue = alertData.inputValue || "";

        if (alertData.inputOptions) {
            config.inputOptions = alertData.inputOptions;
        }

        if (alertData.inputValidator) {
            config.inputValidator = alertData.inputValidator;
        }

        if (alertData.inputLabel) {
            config.inputLabel = alertData.inputLabel;
        }

        if (alertData.inputAttributes) {
            config.inputAttributes = alertData.inputAttributes;
        }
    }

    // Imagen personalizada
    if (alertData.imageUrl) {
        config.imageUrl = alertData.imageUrl;
        config.imageWidth = alertData.imageWidth || 300;
        config.imageHeight = alertData.imageHeight || 200;
        config.imageAlt = alertData.imageAlt || "Imagen personalizada";
    }

    // Footer personalizado
    if (alertData.footer) {
        config.footer = alertData.footer;
    }

    // Callbacks
    if (alertData.onOpen || alertData.didOpen) {
        config.didOpen = alertData.onOpen || alertData.didOpen;
    }

    if (alertData.onClose || alertData.willClose) {
        config.willClose = alertData.onClose || alertData.willClose;
    }

    if (alertData.preConfirm) {
        config.preConfirm = alertData.preConfirm;
    }

    // Mostrar el alert
    return Swal.fire(config);
};

// Métodos utilitarios para diferentes tipos de alertas
window.showAlert = showAlert;

window.showSuccess = (title, message, options = {}) => {
    return showAlert({
        type: "success",
        title,
        message,
        ...options,
    });
};

window.showError = (title, message, options = {}) => {
    return showAlert({
        type: "error",
        title,
        message,
        ...options,
    });
};

window.showWarning = (title, message, options = {}) => {
    return showAlert({
        type: "warning",
        title,
        message,
        ...options,
    });
};

window.showInfo = (title, message, options = {}) => {
    return showAlert({
        type: "info",
        title,
        message,
        ...options,
    });
};

window.showQuestion = (title, message, options = {}) => {
    return showAlert({
        type: "question",
        title,
        message,
        mode: "confirm",
        ...options,
    });
};

window.showToast = (message, type = "success", options = {}) => {
    return showAlert({
        type,
        title: message,
        mode: "toast",
        ...options,
    });
};

window.showConfirm = (title, message, options = {}) => {
    return showAlert({
        type: "question",
        title,
        message,
        mode: "confirm",
        ...options,
    });
};

window.showInput = (title, inputType = "text", options = {}) => {
    return showAlert({
        type: "question",
        title,
        mode: "input",
        input: inputType,
        ...options,
    });
};

window.showLoading = (
    title = "Cargando...",
    message = "Por favor espera un momento"
) => {
    return showAlert({
        title,
        html: message,
        mode: "loading",
    });
};

window.hideLoading = () => {
    Swal.close();
};

// Watcher para alertas desde el backend (flash de Inertia).
//
// En Inertia v2, los closures eager (como 'alert' en HandleInertiaRequests::share())
// se re-evalúan en CADA respuesta — incluyendo partial reloads. Si una página hace
// polling con router.reload(), el alert puede reaparecer en page.props varias veces
// en una secuencia, generando un loop visual ("aparece, desaparece, aparece").
//
// Dedupe por fingerprint de contenido: si el último toast mostrado tiene el mismo
// type+title+message+mode, lo saltamos. Solo se "limpia" cuando el alert pasa a null,
// permitiendo que un alert idéntico vuelva a mostrarse si después de limpiarse vuelve.
let lastFingerprint = null;

const alertFingerprint = (a) => a
    ? `${a.type ?? ''}|${a.title ?? ''}|${a.message ?? a.text ?? ''}|${a.mode ?? ''}|${a.html ?? ''}`
    : null;

watch(
    () => page.props.alert,
    (newAlert) => {
        if (!newAlert) {
            lastFingerprint = null;
            return;
        }
        const fp = alertFingerprint(newAlert);
        if (fp === lastFingerprint) return;
        lastFingerprint = fp;
        setTimeout(() => showAlert(newAlert), 100);
    },
    { deep: true, immediate: true }
);
</script>

<style>
/* Estilos para modo dark */
.swal2-dark {
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.swal2-dark .swal2-title {
    color: inherit !important;
}

.swal2-dark .swal2-html-container {
    color: rgba(255, 255, 255, 0.8) !important;
}

.swal2-dark .swal2-close {
    color: rgba(255, 255, 255, 0.8) !important;
}

.swal2-dark .swal2-close:hover {
    color: rgba(255, 255, 255, 1) !important;
}

/* Botón de cancelar en modo dark */
.dark .swal2-cancel {
    background-color: #4b5563 !important;
    color: #f3f4f6 !important;
}

.dark .swal2-cancel:hover {
    background-color: #6b7280 !important;
}

/* Input en modo dark */
.swal2-dark .swal2-input,
.swal2-dark .swal2-textarea,
.swal2-dark .swal2-select {
    background-color: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.swal2-dark .swal2-input:focus,
.swal2-dark .swal2-textarea:focus,
.swal2-dark .swal2-select:focus {
    border-color: rgba(255, 255, 255, 0.4) !important;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1) !important;
}

/* Placeholder en modo dark */
.swal2-dark .swal2-input::placeholder,
.swal2-dark .swal2-textarea::placeholder {
    color: rgba(255, 255, 255, 0.5) !important;
}

/* Footer en modo dark */
.swal2-dark .swal2-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Timer progress bar en modo dark */
.swal2-dark .swal2-timer-progress-bar {
    background: rgba(255, 255, 255, 0.3) !important;
}

/* Animación suave para modales de confirmación - entrada */
.swal2-smooth-show {
    animation: smoothFadeIn 0.3s ease-out !important;
}

@keyframes smoothFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Animación suave para modales de confirmación - salida */
.swal2-popup.swal2-hide {
    animation: smoothFadeOut 0.15s ease-in forwards !important;
}

@keyframes smoothFadeOut {
    from {
        opacity: 1;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(0.95);
    }
}

/* Toast específico - animación desde la derecha */
.swal2-toast-custom {
    animation: slideInRight 0.4s ease-out !important;
}

/* Animación de entrada personalizada para toast */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Animación de salida para toast */
.swal2-toast.swal2-hide {
    animation: slideOutRight 0.3s ease-in !important;
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Responsive */
@media (max-width: 640px) {
    .swal2-popup {
        width: calc(100% - 20px) !important;
        margin: 10px !important;
    }
}
</style>
