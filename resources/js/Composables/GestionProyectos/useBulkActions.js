import { computed, ref, watch } from 'vue';

/**
 * useBulkActions — Gestiona la selección múltiple de issues y las acciones masivas.
 *
 * @param {import('vue').ComputedRef} filteredIssues — Issues visibles (post-filtro local)
 * @param {import('vue').Ref}         propIssues     — issues prop reactiva del componente
 * @param {function}                  applyFilters   — Función para refrescar la lista tras edición
 */
export function useBulkActions(filteredIssues, propIssues, applyFilters) {
    // ─── Selección ─────────────────────────────────────────────────────────────
    const selectedIssues = ref([]);

    const allVisibleSelected = computed(
        () =>
            filteredIssues.value.length > 0 &&
            filteredIssues.value.every((i) => selectedIssues.value.includes(i.key))
    );

    const someSelected = computed(
        () => selectedIssues.value.length > 0 && !allVisibleSelected.value
    );

    function toggleSelectAll() {
        if (allVisibleSelected.value) {
            selectedIssues.value = [];
        } else {
            selectedIssues.value = filteredIssues.value.map((i) => i.key);
        }
    }

    function clearSelection() {
        selectedIssues.value = [];
    }

    // Limpiar selección al recargar issues desde el servidor
    watch(propIssues, () => {
        selectedIssues.value = [];
    });

    // ─── Estado de modales bulk ────────────────────────────────────────────────
    const bulkLoading    = ref(false);
    const showEditModal  = ref(false);
    const showDeleteModal = ref(false);

    function openBulkEditModal() {
        if (selectedIssues.value.length === 0) return;
        showEditModal.value = true;
    }

    function openDeleteModal() {
        if (selectedIssues.value.length === 0) return;
        showDeleteModal.value = true;
    }

    // ─── Callbacks de completación ─────────────────────────────────────────────
    function onBulkEditCompleted({ succeeded, failed }) {
        if (succeeded.length > 0) {
            clearSelection();
            applyFilters();
            window.showToast(`${succeeded.length} issue(s) actualizados correctamente.`, 'success');
        }
        if (failed.length > 0) {
            console.warn('%c[GestionProyectos] Bulk edit con errores parciales', 'color:#fbbf24', failed);
            window.showToast(`${failed.length} issue(s) no pudieron actualizarse.`, 'warning');
        }
    }

    function onBulkDeleteCompleted({ succeeded, failed }) {
        if (succeeded.length > 0) {
            clearSelection();
            applyFilters();
            window.showToast(`${succeeded.length} issue(s) eliminados.`, 'success');
        }
        if (failed.length > 0) {
            window.showToast(`${failed.length} issue(s) no pudieron eliminarse.`, 'warning');
        }
    }

    return {
        selectedIssues,
        allVisibleSelected,
        someSelected,
        toggleSelectAll,
        clearSelection,
        bulkLoading,
        showEditModal,
        showDeleteModal,
        openBulkEditModal,
        openDeleteModal,
        onBulkEditCompleted,
        onBulkDeleteCompleted,
    };
}
