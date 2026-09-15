/**
 * Asigna un color consistente a cada label según su nombre.
 * Usa hash determinístico para que el mismo label siempre tenga el mismo color
 * incluso después de recargar la página.
 *
 * Retorna clases tailwind compuestas para bg + text + border.
 */
const PALETTE = [
    {
        bg: 'bg-blue-100 dark:bg-blue-900/30',
        text: 'text-blue-800 dark:text-blue-200',
        border: 'border-blue-300 dark:border-blue-500/50',
    },
    {
        bg: 'bg-green-100 dark:bg-green-900/30',
        text: 'text-green-800 dark:text-green-200',
        border: 'border-green-300 dark:border-green-500/50',
    },
    {
        bg: 'bg-purple-100 dark:bg-purple-900/30',
        text: 'text-purple-800 dark:text-purple-200',
        border: 'border-purple-300 dark:border-purple-500/50',
    },
    {
        bg: 'bg-orange-100 dark:bg-orange-900/30',
        text: 'text-orange-800 dark:text-orange-200',
        border: 'border-orange-300 dark:border-orange-500/50',
    },
    {
        bg: 'bg-pink-100 dark:bg-pink-900/30',
        text: 'text-pink-800 dark:text-pink-200',
        border: 'border-pink-300 dark:border-pink-500/50',
    },
    {
        bg: 'bg-indigo-100 dark:bg-indigo-900/30',
        text: 'text-indigo-800 dark:text-indigo-200',
        border: 'border-indigo-300 dark:border-indigo-500/50',
    },
    {
        bg: 'bg-teal-100 dark:bg-teal-900/30',
        text: 'text-teal-800 dark:text-teal-200',
        border: 'border-teal-300 dark:border-teal-500/50',
    },
    {
        bg: 'bg-rose-100 dark:bg-rose-900/30',
        text: 'text-rose-800 dark:text-rose-200',
        border: 'border-rose-300 dark:border-rose-500/50',
    },
];

export function getLabelColor(label) {
    if (!label) return 'bg-gray-100 text-gray-600 border-gray-200';
    // Hash determinístico simple para que el mismo label tenga siempre el mismo color
    let hash = 0;
    for (let i = 0; i < label.length; i++) {
        hash = (hash << 5) - hash + label.charCodeAt(i);
        hash |= 0;
    }
    const palette = PALETTE[Math.abs(hash) % PALETTE.length];
    return `${palette.bg} ${palette.text} ${palette.border}`;
}
