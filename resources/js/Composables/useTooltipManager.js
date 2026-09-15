import { ref, onMounted, onUnmounted } from "vue";

// Estado global compartido entre todas las instancias
const activeTooltipId = ref(null);
let clickListenerAdded = false;

// Función global para manejar clicks fuera
function handleGlobalClick(event) {
    // Si no hay tooltip activo, no hacer nada
    if (!activeTooltipId.value) return;

    // Si el click fue dentro de un tooltip, no cerrar
    if (event.target.closest(".tooltip-interactive")) return;

    // Si el click fue en un botón de ProfileChip, dejar que el component maneje
    if (event.target.closest("button")) return;

    // En cualquier otro caso, cerrar el tooltip activo
    activeTooltipId.value = null;
}

export function useTooltipManager() {
    const openTooltip = (tooltipId) => {
        activeTooltipId.value = tooltipId;

        // Agregar el listener global solo una vez
        if (!clickListenerAdded) {
            document.addEventListener("click", handleGlobalClick);
            clickListenerAdded = true;
        }
    };

    const closeTooltip = () => {
        activeTooltipId.value = null;
    };

    const isActive = (tooltipId) => {
        return activeTooltipId.value === tooltipId;
    };

    return {
        activeTooltipId,
        openTooltip,
        closeTooltip,
        isActive,
    };
}