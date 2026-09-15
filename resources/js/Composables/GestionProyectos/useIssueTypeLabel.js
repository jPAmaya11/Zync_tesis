// Etiquetas de VISUALIZACIÓN de los tipos de actividad.
// El valor real (config + BD) NO cambia: p. ej. se sigue guardando/validando 'Error';
// aquí solo se muestra otra etiqueta en la UI (selector, tabla, etc.).
const ISSUE_TYPE_LABELS = {
    Error: 'Bug',
};

/** Devuelve la etiqueta visible de un tipo de actividad (cae al mismo nombre si no hay alias). */
export function issueTypeLabel(name) {
    return ISSUE_TYPE_LABELS[name] ?? name ?? '';
}
