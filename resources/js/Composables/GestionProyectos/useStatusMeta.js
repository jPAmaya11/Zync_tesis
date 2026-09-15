/**
 * useStatusMeta — Construye el objeto de estado completo (name + category_key + color_class)
 * espejando exactamente ProyectoDTO::mapStatus() del backend.
 *
 * Se usa al actualizar el estado inline en memoria: actualizar solo `status.name` deja el
 * `color_class` desincronizado (el chip no cambia de color hasta recargar). Con esto, el
 * color del chip se actualiza al instante junto con el nombre.
 */

const COLOR_BY_CATEGORY = {
    new:           'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800/40 dark:text-gray-300 dark:border-gray-700',
    indeterminate: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800',
    done:          'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-800 dark:text-emerald-100 dark:border-emerald-800',
    undefined:     'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-800',
};

function categoryForStatus(name) {
    if (name === 'Pendiente') return 'new';
    if (['En Curso', 'En Pausa', 'En Revisión'].includes(name)) return 'indeterminate';
    if (name === 'Finalizado') return 'done';
    return 'undefined'; // Reprogramado, Cancelado y cualquier otro → ámbar
}

/**
 * Devuelve el objeto status completo para un nombre de estado.
 * @param {string|null} name
 * @returns {{id: null, name: string, category_key: string, color_class: string}|null}
 */
export function mapStatusMeta(name) {
    if (name == null) return null;
    const categoryKey = categoryForStatus(name);
    return {
        id: null,
        name,
        category_key: categoryKey,
        color_class: COLOR_BY_CATEGORY[categoryKey],
    };
}
