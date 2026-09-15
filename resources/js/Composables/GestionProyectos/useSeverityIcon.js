// Ícono SVG por nivel de severidad — fuente única para Impacto y Prioridad.
// Mapea por palabra clave, así cubre Impacto (Crítico/Alto/Medio/Bajo/Sin impacto)
// y Prioridad (Alta/Media/Baja, y los de Jira: Highest/High/Medium/Low/Lowest).
// Devuelve { cls, path } para pintar un <svg> con stroke=currentColor.
export function severityIcon(name) {
    const n = (name ?? '').toString().toLowerCase().trim();

    if (n.includes('crític') || n.includes('critic') || n.includes('highest'))
        return { cls: 'text-red-500', path: 'M5 15l7-7 7 7M5 10l7-7 7 7' };   // mismo rojo que Alto, doble chevron arriba
    if (n.includes('alt') || n.includes('high'))
        return { cls: 'text-red-500', path: 'M5 14l7-7 7 7' };                 // chevron arriba
    if (n.includes('medi'))
        return { cls: 'text-amber-500', path: 'M5 9h14M5 15h14' };            // igual (=)
    if (n.includes('lowest'))
        return { cls: 'text-blue-600', path: 'M5 9l7 7 7-7M5 14l7 7 7-7' };   // doble chevron abajo
    if (n.includes('baj') || n.includes('low'))
        return { cls: 'text-blue-500', path: 'M19 10l-7 7-7-7' };             // chevron abajo

    return { cls: 'text-gray-400', path: 'M5 12h14' };                        // guion (Sin impacto / desconocido)
}
