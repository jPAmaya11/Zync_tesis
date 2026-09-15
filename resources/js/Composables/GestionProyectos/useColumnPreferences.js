import { computed, ref, watch } from 'vue';

/**
 * Hook para gestionar las preferencias de columnas dinámicas de un proyecto.
 *
 * Encapsula:
 *  - Estado local sincronizado con la prop `columnPreferences` del servidor
 *  - Computed `visibleColumns` (las marcadas como visible, ordenadas)
 *  - Computed `allColumnsSorted` (todas, ordenadas — para el panel)
 *  - Persistencia en backend via POST /gestion-proyectos/{project}/column-preferences
 *  - Helpers: toggleColumnVisibility, moveColumnUp, moveColumnDown, addColumn
 *
 * Uso:
 *   const cols = useColumnPreferences(props, () => form.project);
 *   cols.allColumnsSorted.value
 *   cols.toggleColumnVisibility('priority')
 *
 * @param {Object} props        Las props del componente padre (debe tener `columnPreferences`)
 * @param {Function} getProjectKey  Función que devuelve la project key actual (reactiva)
 */
export function useColumnPreferences(props, getProjectKey) {
    // Si el servidor no manda preferencias (proyecto recién creado), las derivamos
    // del catalogColumns usando `visible_by_default` — así no aparece tabla vacía.
    const deriveFromCatalog = () =>
        props.catalogColumns.map((c, idx) => ({
            key: c.key,
            name: c.name,
            visible: c.visible_by_default ?? true,
            order: idx + 1,
        }));

    const localColumnPrefs = ref(
        props.columnPreferences.length > 0
            ? [...props.columnPreferences]
            : deriveFromCatalog()
    );
    const savingPrefs = ref(false);

    // Etiqueta de PRESENTACIÓN: 'fecha_entrega' se muestra como "Staging" (decisión UX).
    // Solo cambia la vista: la key y el name persistido en BD/preferencias quedan igual
    // (persist() envía localColumnPrefs crudo, sin este override).
    const displayColName = (c) =>
        c.key === 'fecha_entrega' ? { ...c, name: 'Fecha Subida Staging' } : c;

    /** Columnas visibles ordenadas por `order` */
    const visibleColumns = computed(() =>
        localColumnPrefs.value
            .filter((c) => c.visible)
            .slice()
            .sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
            .map(displayColName)
    );

    /** Todas las columnas ordenadas (para el panel de configuración) */
    const allColumnsSorted = computed(() =>
        localColumnPrefs.value
            .slice()
            .sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
            .map(displayColName)
    );

    async function persist() {
        const project = getProjectKey();
        if (!project) return;
        savingPrefs.value = true;
        try {
            // axios envía la cookie XSRF-TOKEN (siempre fresca) → evita el 419 del <meta>.
            await window.axios.post(
                route('gestion-proyectos.column-preferences.save', project),
                { columns: localColumnPrefs.value }
            );
        } catch (e) {
            console.warn(
                '[GestionProyectos] Error guardando preferencias de columnas',
                e
            );
        } finally {
            savingPrefs.value = false;
        }
    }

    function toggleColumnVisibility(colKey) {
        const col = localColumnPrefs.value.find((c) => c.key === colKey);
        if (col) {
            col.visible = !col.visible;
            persist();
        }
    }

    function moveColumnUp(colKey) {
        const sorted = localColumnPrefs.value
            .slice()
            .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
        const idx = sorted.findIndex((c) => c.key === colKey);
        if (idx <= 0) return;
        const tmp = sorted[idx].order;
        sorted[idx].order = sorted[idx - 1].order;
        sorted[idx - 1].order = tmp;
        localColumnPrefs.value = sorted;
        persist();
    }

    function moveColumnDown(colKey) {
        const sorted = localColumnPrefs.value
            .slice()
            .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
        const idx = sorted.findIndex((c) => c.key === colKey);
        if (idx === -1 || idx >= sorted.length - 1) return;
        const tmp = sorted[idx].order;
        sorted[idx].order = sorted[idx + 1].order;
        sorted[idx + 1].order = tmp;
        localColumnPrefs.value = sorted;
        persist();
    }

    /** Añadir una columna al final del orden (visible por defecto). */
    function addColumn(col) {
        const maxOrder = localColumnPrefs.value.reduce(
            (m, c) => Math.max(m, c.order ?? 0),
            0
        );
        localColumnPrefs.value.push({
            ...col,
            visible: true,
            order: maxOrder + 1,
        });
        persist();
    }

    // Recargar preferencias cuando el servidor retorna datos frescos (cambio de proyecto)
    watch(
        () => props.columnPreferences,
        (fresh) => {
            localColumnPrefs.value =
                fresh.length > 0 ? [...fresh] : deriveFromCatalog();
        },
        { deep: true }
    );

    return {
        localColumnPrefs,
        savingPrefs,
        visibleColumns,
        allColumnsSorted,
        displayColName,
        toggleColumnVisibility,
        moveColumnUp,
        moveColumnDown,
        addColumn,
        persist,
    };
}
